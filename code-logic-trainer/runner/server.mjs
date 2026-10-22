import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { timingSafeEqual, randomUUID } from 'node:crypto';
import { chmod, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const port = Number(process.env.PORT || 8080);
const token = process.env.CODE_RUNNER_TOKEN || '';
const image = process.env.SANDBOX_IMAGE || 'code-logic-php-sandbox:1';
const maxConcurrentRuns = Number(process.env.MAX_CONCURRENT_RUNS || 3);
const maxCodeBytes = 12_000;
const maxBodyBytes = 96 * 1024;
const executionTimeoutMs = 4_000;
const outputLimitBytes = 128 * 1024;
const resultMarker = '__CODE_RUNNER_RESULT__:';
let activeRuns = 0;

if (token.length < 32) {
    throw new Error('CODE_RUNNER_TOKEN must contain at least 32 characters.');
}
if (!/^[a-zA-Z0-9._/:@-]+$/.test(image)) {
    throw new Error('SANDBOX_IMAGE contains unsupported characters.');
}

function sendJson(response, status, body) {
    response.writeHead(status, {
        'content-type': 'application/json; charset=utf-8',
        'cache-control': 'no-store',
        'x-content-type-options': 'nosniff',
    });
    response.end(JSON.stringify(body));
}

async function readJson(request) {
    const chunks = [];
    let bytes = 0;
    for await (const chunk of request) {
        bytes += chunk.length;
        if (bytes > maxBodyBytes) {
            throw Object.assign(new Error('Request is too large.'), { status: 413 });
        }
        chunks.push(chunk);
    }

    try {
        return JSON.parse(Buffer.concat(chunks).toString('utf8'));
    } catch {
        throw Object.assign(new Error('Request body must be valid JSON.'), { status: 400 });
    }
}

function isAuthorized(request) {
    const authorization = request.headers.authorization || '';
    const prefix = 'Bearer ';
    if (!authorization.startsWith(prefix)) return false;

    const provided = Buffer.from(authorization.slice(prefix.length));
    const expected = Buffer.from(token);
    return provided.length === expected.length && timingSafeEqual(provided, expected);
}

function validatePayload(payload) {
    if (!payload || typeof payload !== 'object'
        || typeof payload.code !== 'string'
        || Buffer.byteLength(payload.code, 'utf8') > maxCodeBytes
        || !/^[A-Za-z_][A-Za-z0-9_]{0,63}$/.test(payload.function_name)
        || !Array.isArray(payload.tests)
        || payload.tests.length < 1
        || payload.tests.length > 30) {
        throw Object.assign(new Error('Invalid challenge run request.'), { status: 422 });
    }

    for (const test of payload.tests) {
        if (!test || typeof test.label !== 'string'
            || !Array.isArray(test.arguments)
            || !Object.hasOwn(test, 'expected')) {
            throw Object.assign(new Error('Invalid test case.'), { status: 422 });
        }
    }
}

function runDocker(args, stdinData, containerName) {
    return new Promise((resolve, reject) => {
        const child = spawn('docker', args, { stdio: ['pipe', 'pipe', 'pipe'] });
        const stdout = [];
        const stderr = [];
        let outputBytes = 0;
        let timedOut = false;
        let outputExceeded = false;
        let finished = false;

        const timer = setTimeout(() => {
            timedOut = true;
            child.kill('SIGKILL');
            const cleanup = spawn('docker', ['rm', '-f', containerName], { stdio: 'ignore' });
            cleanup.on('error', () => {});
        }, executionTimeoutMs);

        const capture = (target) => (chunk) => {
            outputBytes += chunk.length;
            if (outputBytes > outputLimitBytes) {
                outputExceeded = true;
                child.kill('SIGKILL');
                return;
            }
            target.push(chunk);
        };

        child.stdout.on('data', capture(stdout));
        child.stderr.on('data', capture(stderr));
        child.on('error', (error) => finish(error));
        child.on('close', (code) => finish(null, code));
        child.stdin.on('error', () => {});
        child.stdin.end(stdinData);

        function finish(error, exitCode) {
            if (finished) return;
            finished = true;
            clearTimeout(timer);

            const result = {
                stdout: Buffer.concat(stdout).toString('utf8'),
                stderr: Buffer.concat(stderr).toString('utf8'),
            };
            if (error) return reject(error);
            if (timedOut) return reject(Object.assign(new Error('Execution exceeded the time limit.'), { code: 'TIMEOUT' }));
            if (outputExceeded) return reject(Object.assign(new Error('Execution produced too much output.'), { code: 'OUTPUT_LIMIT' }));
            if (exitCode !== 0) return reject(Object.assign(new Error('Sandbox process failed.'), { code: 'EXECUTION_FAILED', result }));
            resolve(result);
        }
    });
}

async function execute(payload) {
    const directory = await mkdtemp(join(tmpdir(), 'code-challenge-'));
    const containerName = `code-challenge-${randomUUID()}`;

    try {
        await chmod(directory, 0o755);
        await writeFile(join(directory, 'solution.php'), payload.code, { mode: 0o444 });
        await writeFile(join(directory, 'cases.json'), JSON.stringify({
            function_name: payload.function_name,
            arguments: payload.tests.map((test) => test.arguments),
        }), { mode: 0o444 });

        const args = [
            'run', '--rm', '--interactive',
            '--name', containerName,
            '--network', 'none',
            '--memory', '128m',
            '--memory-swap', '128m',
            '--cpus', '0.5',
            '--pids-limit', '32',
            '--read-only',
            '--tmpfs', '/tmp:rw,noexec,nosuid,size=8m,mode=1777',
            '--user', '65534:65534',
            '--cap-drop', 'ALL',
            '--security-opt', 'no-new-privileges:true',
            '--ulimit', 'nofile=64:64',
            '--mount', `type=bind,src=${directory},dst=/work,readonly`,
            image,
            'php',
            '-d', 'memory_limit=64M',
            '-d', 'max_execution_time=3',
            '-d', 'open_basedir=/work:/opt:/tmp',
            '-d', 'disable_functions=exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,dl,putenv,curl_exec,curl_init,fsockopen,pfsockopen,stream_socket_client,stream_socket_server,mail,ini_set,set_time_limit',
            '/opt/execute.php',
        ];
        const { stdout, stderr } = await runDocker(
            args,
            JSON.stringify({ arguments: payload.tests.map((test) => test.arguments) }),
            containerName
        );

        const resultLine = stdout.split(/\r?\n/).reverse()
            .find((line) => line.startsWith(resultMarker));
        if (!resultLine) {
            const diagnostic = `${stdout}\n${stderr}`;
            return {
                passed: false,
                output: diagnostic.includes('Parse error') ? 'PHP syntax error. Check your solution and try again.' : 'The solution could not be executed. Check for syntax or runtime errors.',
                results: payload.tests.map((test) => ({ label: test.label, passed: false })),
            };
        }

        let execution;
        try {
            execution = JSON.parse(resultLine.slice(resultMarker.length));
        } catch {
            throw new Error('The sandbox returned an invalid test result.');
        }
        const values = execution?.results;
        if (!Array.isArray(values) || values.length !== payload.tests.length) {
            throw new Error('The sandbox returned an incomplete test result.');
        }

        const results = payload.tests.map((test, index) => ({
            label: test.label,
            passed: values[index]?.ok === true
                && JSON.stringify(values[index].value) === JSON.stringify(test.expected),
        }));

        return {
            passed: results.every((test) => test.passed),
            results,
            output: [
                results.every((test) => test.passed)
                    ? 'All automated tests passed.'
                    : `${results.filter((test) => test.passed).length} of ${results.length} tests passed.`,
                execution.output,
                ...values.map((value) => value.output || ''),
            ].filter(Boolean).join('\n').slice(0, 2000),
        };
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
}

const server = createServer(async (request, response) => {
    if (request.method === 'GET' && request.url === '/healthz') {
        return sendJson(response, 200, { ok: true });
    }

    if (request.method !== 'POST' || request.url !== '/run') {
        return sendJson(response, 404, { message: 'Not found.' });
    }

    if (!isAuthorized(request)) {
        return sendJson(response, 401, { message: 'Unauthorized.' });
    }
    if (activeRuns >= maxConcurrentRuns) {
        return sendJson(response, 429, { message: 'Runner is busy. Try again shortly.' });
    }

    let acquiredRunSlot = false;
    try {
        const payload = await readJson(request);
        validatePayload(payload);
        activeRuns++;
        acquiredRunSlot = true;
        const result = await execute(payload);
        return sendJson(response, 200, result);
    } catch (error) {
        const status = error.status || (error.code === 'TIMEOUT' || error.code === 'OUTPUT_LIMIT' ? 200 : 500);
        const body = status === 200
            ? {
                passed: false,
                output: error.code === 'TIMEOUT'
                    ? 'Execution timed out (4-second limit).'
                    : 'Execution stopped because it produced too much output.',
                results: [],
            }
            : { message: status === 500 ? 'Sandbox execution failed.' : error.message };
        if (status === 500) console.error(error);
        return sendJson(response, status, body);
    } finally {
        if (acquiredRunSlot) activeRuns--;
    }
});

server.listen(port, '0.0.0.0');
