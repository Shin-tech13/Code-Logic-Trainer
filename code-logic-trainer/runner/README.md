# Isolated PHP challenge runner

Laravel never evaluates submitted PHP. It sends the student's code and the trusted challenge tests from the database to this private runner. The runner starts a disposable container with no network, a read-only root filesystem, 128 MB memory, half a CPU, a 32-process limit, and a four-second wall-clock limit. The expected answers stay in the runner service and are not mounted into the code container.

## Local setup

Run these commands from the Laravel project root:

```sh
docker build -t code-logic-php-sandbox:1 -f runner/Dockerfile.sandbox runner
```

Set `CODE_RUNNER_TOKEN` in `.env` to a random value of at least 32 characters. Configure:

```dotenv
CODE_RUNNER_URL=http://127.0.0.1:8090
CODE_RUNNER_TOKEN=your-long-random-token
```

Start the runner using the same token:

```sh
docker compose --env-file .env -f compose.runner.yaml up --build -d
```

Run Laravel and apply database setup:

```sh
php artisan migrate
php artisan db:seed --class=CodeChallengeSeeder
```

The runner's HTTP port is bound to localhost for local development. If Laravel itself runs in a container, configure it to reach the runner over a private Docker network rather than exposing the runner publicly.

## Production isolation requirements

The runner requires access to a Docker daemon. Access to `/var/run/docker.sock` is effectively root access to its host. **Do not mount the production application host's Docker socket into this service.** Run the runner on a dedicated, disposable VM/host with no production data or credentials, restrict `/run` to a private network, use a long random `CODE_RUNNER_TOKEN`, and set Laravel's `CODE_RUNNER_URL` to that private endpoint. Do not expose the runner port to the public internet.

For Render, deploy Laravel and the runner separately. The runner needs a Docker-capable host; the normal Laravel web service is not the sandbox. Keep the runner token only in environment-variable settings, never in source control. Apply database migrations and seed the challenges with the project's deployment commands.

Challenges are stored in `code_challenges`; test inputs/expected values are managed server-side. Keep challenge definitions trusted and do not accept test cases or expected results from browser requests.
