<?php

use App\Exceptions\InvalidQuizOutputException;
use App\Services\ExamQuestionParser;

function sampleFiftyQuestionExam(): string
{
    $lines = [
        'CODE LOGIC TRAINER — MIDTERM EXAM',
        'Name: _____________________',
        'Section I: Multiple Choice',
        'Choose the best answer for each item.',
    ];

    for ($number = 1; $number <= 50; $number++) {
        $lines[] = "{$number}. Which answer belongs to question {$number}?";
        $lines[] = "A. Choice A for {$number}";
        $lines[] = "B. Choice B for {$number}";
        $lines[] = "C. Choice C for {$number}";
        $lines[] = "D. Choice D for {$number}";
        if ($number === 25) {
            $lines[] = 'Page 2 of 3';
            $lines[] = 'CODE LOGIC TRAINER — MIDTERM EXAM';
        }
    }

    $lines[] = 'Answer Key';
    for ($number = 1; $number <= 50; $number++) {
        $lines[] = "{$number}. ".chr(ord('A') + (($number - 1) % 4));
    }

    return implode("\n", $lines);
}

test('numbered exam parser ignores headings and maps 50 answer-key entries through page breaks', function () {
    $parser = app(ExamQuestionParser::class);
    $questions = $parser->parse(sampleFiftyQuestionExam(), 5, 'multiple_choice');

    expect($questions)->toHaveCount(5)
        ->and($questions[0]['question_text'])->toBe('Which answer belongs to question 1?')
        ->and($questions[0]['correct_answer'])->toBe('A. Choice A for 1')
        ->and($questions[1]['correct_answer'])->toBe('B. Choice B for 2')
        ->and($questions[4]['correct_answer'])->toBe('A. Choice A for 5')
        ->and($questions[0]['explanation'])->toBe('No explanation provided.')
        ->and($questions[0]['needs_review'])->toBeFalse();

    expect($parser->parse(sampleFiftyQuestionExam(), null, 'multiple_choice'))->toHaveCount(50);
});

test('numbered exam parser rejects an answer key that cannot map every selected question', function () {
    $exam = str_replace("1. A\n", "1. Z\n", sampleFiftyQuestionExam());

    expect(fn () => app(ExamQuestionParser::class)->parse($exam, 50, 'multiple_choice'))
        ->toThrow(InvalidQuizOutputException::class);
});

test('numbered exam parser exposes safe rejection reason codes without including document text', function () {
    $parser = app(ExamQuestionParser::class);
    $cases = [
        [
            str_replace('Answer Key', 'Answers', sampleFiftyQuestionExam()),
            'missing_answer_key',
        ],
        [
            str_replace("1. A\n", "1: A\n", sampleFiftyQuestionExam()),
            'answer_key_format_unrecognized',
        ],
        [
            str_replace("D. Choice D for 1\n", '', sampleFiftyQuestionExam()),
            'missing_or_invalid_choices',
        ],
        [
            sampleFiftyQuestionExam()."\n51. A",
            'answer_key_question_mismatch',
        ],
    ];

    foreach ($cases as [$exam, $reasonCode]) {
        try {
            $parser->parse($exam, 50, 'multiple_choice');
            test()->fail("Expected parser rejection: {$reasonCode}");
        } catch (InvalidQuizOutputException $exception) {
            expect($exception->reasonCode)->toBe($reasonCode)
                ->and($exception->getMessage())->not->toContain('Choice A for 1');
        }
    }
});

test('numbered exam parser leaves unstructured material to the configured provider', function () {
    expect(app(ExamQuestionParser::class)->parse(
        'A lesson about loops without numbered questions.',
        5,
        'multiple_choice',
    ))->toBeNull();
});
