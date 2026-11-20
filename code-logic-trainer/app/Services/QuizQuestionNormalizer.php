<?php

namespace App\Services;

use App\Exceptions\InvalidQuizOutputException;
use JsonException;

class QuizQuestionNormalizer
{
    /**
     * @return array<int, array{question_text: string, options: array<int,string>, question_type: string, correct_answer: string, accepted_answers: array<int,string>, explanation: string, needs_review: bool}>
     */
    public function normalize(string $raw, string $format, int $limit, string $provider): array
    {
        $raw = trim($raw);
        if (mb_strlen($raw) > 2_000_000) {
            throw new InvalidQuizOutputException($provider);
        }

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $raw, $matches) === 1) {
            $raw = trim($matches[1]);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidQuizOutputException($provider);
        }

        if (is_array($decoded) && ! array_is_list($decoded) && is_array($decoded['questions'] ?? null)) {
            $decoded = $decoded['questions'];
        }
        if (! is_array($decoded) || ! array_is_list($decoded) || $decoded === []) {
            throw new InvalidQuizOutputException($provider);
        }

        $valid = [];
        $seen = [];
        foreach ($decoded as $item) {
            if (! is_array($item)
                || ! is_string($item['question_text'] ?? null)
                || trim($item['question_text']) === ''
                || mb_strlen(trim($item['question_text'])) > 3000
                || ! is_string($item['correct_answer'] ?? null)
                || trim($item['correct_answer']) === ''
                || ! is_string($item['explanation'] ?? null)
                || trim($item['explanation']) === ''
                || mb_strlen(trim($item['explanation'])) > 3000
                || ! is_bool($item['needs_review'] ?? null)) {
                throw new InvalidQuizOutputException($provider);
            }

            $questionText = trim($item['question_text']);
            $normalizedQuestion = mb_strtolower(
                preg_replace('/\s+/u', ' ', $questionText) ?? $questionText,
                'UTF-8',
            );
            if (isset($seen[$normalizedQuestion])) {
                throw new InvalidQuizOutputException($provider);
            }

            $options = $item['options'] ?? null;
            if (! is_array($options) || ! array_is_list($options)) {
                throw new InvalidQuizOutputException($provider);
            }
            $rawVariants = $item['accepted_answers'] ?? null;
            if (! is_array($rawVariants) || ! array_is_list($rawVariants) || count($rawVariants) > 10) {
                throw new InvalidQuizOutputException($provider);
            }
            $options = array_map(
                static fn ($option) => is_string($option) ? trim($option) : '',
                $options,
            );
            $correctAnswer = trim($item['correct_answer']);
            $acceptedAnswers = [];

            if ($format === 'multiple_choice') {
                if (count($options) !== 4
                    || $rawVariants !== []
                    || in_array('', $options, true)
                    || count(array_filter($options, static fn ($option) => mb_strlen($option) > 500)) > 0
                    || count(array_unique(array_map(
                        static fn ($option) => mb_strtolower($option, 'UTF-8'),
                        $options,
                    ))) !== 4) {
                    throw new InvalidQuizOutputException($provider);
                }

                if (! in_array($correctAnswer, $options, true)
                    && preg_match('/^([A-D])(?:[.)])?$/i', $correctAnswer, $answerLabel) === 1) {
                    $correctAnswer = $options[ord(strtoupper($answerLabel[1])) - ord('A')];
                }
                if (! in_array($correctAnswer, $options, true)) {
                    throw new InvalidQuizOutputException($provider);
                }
            } elseif ($format === 'fill_in_the_blank') {
                if ($options !== []
                    || preg_match('/(?:_{3,}|\[\s*blank\s*\])/i', $questionText) !== 1
                    || mb_strlen($correctAnswer) > 200) {
                    throw new InvalidQuizOutputException($provider);
                }

                foreach ($rawVariants as $variant) {
                    if (! is_string($variant) || trim($variant) === '' || mb_strlen(trim($variant)) > 200) {
                        throw new InvalidQuizOutputException($provider);
                    }
                    $acceptedAnswers[] = trim($variant);
                }
                $acceptedAnswers = array_values(array_unique(array_merge([$correctAnswer], $acceptedAnswers)));
            } else {
                throw new InvalidQuizOutputException($provider);
            }

            $valid[] = [
                'question_text' => $questionText,
                'options' => $options,
                'question_type' => $format,
                'correct_answer' => $correctAnswer,
                'accepted_answers' => $acceptedAnswers,
                'explanation' => trim($item['explanation']),
                'needs_review' => $item['needs_review'] ?? true,
            ];
            $seen[$normalizedQuestion] = true;
        }

        return array_slice($valid, 0, $limit);
    }
}
