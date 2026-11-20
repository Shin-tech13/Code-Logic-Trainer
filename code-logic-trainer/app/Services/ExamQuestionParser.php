<?php

namespace App\Services;

use App\Exceptions\InvalidQuizOutputException;

class ExamQuestionParser
{
    /**
     * Parse a numbered multiple-choice exam only when its answer key is explicit.
     *
     * @return array<int, array{question_text: string, options: array<int,string>, question_type: string, correct_answer: string, accepted_answers: array<int,string>, explanation: string, needs_review: bool}>|null
     */
    public function parse(string $sourceText, ?int $count, string $format): ?array
    {
        if ($format !== 'multiple_choice') {
            return null;
        }

        $lines = preg_split('/\R/u', str_replace("\f", "\n", $sourceText)) ?: [];
        $answerKeyLine = null;
        $answerKeyHeading = '';
        foreach ($lines as $index => $line) {
            if (preg_match('/\b(?:answer\s*key|key\s+to\s+(?:the\s+)?(?:exam|test|quiz)|correct\s+answers)\b/i', $line) === 1) {
                $answerKeyLine = $index;
                $answerKeyHeading = preg_replace(
                    '/^.*?\b(?:answer\s*key|key\s+to\s+(?:the\s+)?(?:exam|test|quiz)|correct\s+answers)\b\s*[:.-]?\s*/i',
                    '',
                    trim($line),
                ) ?? '';
                break;
            }
        }

        if ($answerKeyLine === null) {
            return null;
        }

        $answers = $this->parseAnswerKey(array_merge(
            $answerKeyHeading === '' ? [] : [$answerKeyHeading],
            array_slice($lines, $answerKeyLine + 1),
        ));
        if ($answers === []) {
            throw new InvalidQuizOutputException('local_exam_parser');
        }

        $blocks = $this->questionBlocks(array_slice($lines, 0, $answerKeyLine));
        $structured = [];
        foreach ($blocks as $number => $block) {
            $question = $this->parseQuestionBlock($number, $block);
            if ($question !== null) {
                $structured[$number] = $question;
            }
        }

        if (count($structured) < 2) {
            throw new InvalidQuizOutputException('local_exam_parser');
        }

        $numbers = array_keys($structured);
        $first = min($numbers);
        $last = max($numbers);
        foreach (range($first, $last) as $number) {
            if (! array_key_exists($number, $structured)) {
                throw new InvalidQuizOutputException('local_exam_parser');
            }
        }

        $limit = $count !== null && $count > 0 ? min($count, 50) : 50;
        $questions = [];
        foreach ($structured as $number => $question) {
            if (count($questions) >= $limit) {
                break;
            }
            if (! isset($answers[$number])) {
                throw new InvalidQuizOutputException('local_exam_parser');
            }

            $answerIndex = ord($answers[$number]) - ord('A');
            $correctAnswer = $question['options'][$answerIndex] ?? null;
            if (! is_string($correctAnswer)) {
                throw new InvalidQuizOutputException('local_exam_parser');
            }

            $questions[] = [
                'question_text' => $question['question_text'],
                'options' => $question['options'],
                'question_type' => 'multiple_choice',
                'correct_answer' => $correctAnswer,
                'accepted_answers' => [],
                'explanation' => 'No explanation provided.',
                'needs_review' => false,
            ];
        }

        if ($count !== null && $count > 0 && count($questions) < min($count, 50)) {
            throw new InvalidQuizOutputException('local_exam_parser');
        }

        return $questions;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array<int, string>>
     */
    private function questionBlocks(array $lines): array
    {
        $blocks = [];
        $lineCounts = [];
        foreach ($lines as $line) {
            $normalized = mb_strtolower(trim($line), 'UTF-8');
            if ($normalized !== '' && preg_match('/^\d{1,3}\s*[.)]|^[A-D]\s*[.)]/i', $normalized) !== 1) {
                $lineCounts[$normalized] = ($lineCounts[$normalized] ?? 0) + 1;
            }
        }

        $currentNumber = null;
        foreach ($lines as $line) {
            $line = trim($line);
            $normalizedLine = mb_strtolower($line, 'UTF-8');
            if ($this->isPageOrStudentDetail($line)) {
                continue;
            }
            if (($lineCounts[$normalizedLine] ?? 0) > 1) {
                continue;
            }
            if (preg_match('/^(\d{1,3})\s*[.)]\s*(.*)$/u', $line, $match) === 1) {
                $currentNumber = (int) $match[1];
                $blocks[$currentNumber] = [$match[2]];

                continue;
            }
            if ($currentNumber !== null && $line !== '') {
                $blocks[$currentNumber][] = $line;
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{question_text: string, options: array<int,string>}|null
     */
    private function parseQuestionBlock(int $number, array $lines): ?array
    {
        $questionParts = [];
        $options = [];
        $currentOption = null;
        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-D])\s*[.)]\s*(.+?)\s*$/iu', $line, $match) === 1) {
                $label = strtoupper($match[1]);
                $index = ord($label) - ord('A');
                if (isset($options[$index])) {
                    throw new InvalidQuizOutputException('local_exam_parser');
                }
                $options[$index] = $label.'. '.trim($match[2]);
                $currentOption = $index;

                continue;
            }

            if ($currentOption !== null) {
                $options[$currentOption] .= ' '.trim($line);
            } elseif (! $this->isPageOrStudentDetail(trim($line))) {
                $questionParts[] = trim($line);
            }
        }

        if ($options === []) {
            return null;
        }
        if (count($options) !== 4 || array_keys($options) !== [0, 1, 2, 3]) {
            throw new InvalidQuizOutputException('local_exam_parser');
        }

        $questionText = trim(implode(' ', array_filter($questionParts)));
        if ($questionText === '' || mb_strlen($questionText) > 3000) {
            throw new InvalidQuizOutputException('local_exam_parser');
        }

        foreach ($options as $option) {
            if (mb_strlen($option) > 500) {
                throw new InvalidQuizOutputException('local_exam_parser');
            }
        }

        return [
            'question_text' => $questionText,
            'options' => array_values($options),
        ];
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function parseAnswerKey(array $lines): array
    {
        $text = implode("\n", $lines);
        preg_match_all('/(?:^|\s)(\d{1,3})\s*[.)-]?\s*([A-D])(?=\s|[,;]|$)/iu', $text, $matches, PREG_SET_ORDER);
        $answers = [];
        foreach ($matches as $match) {
            $number = (int) $match[1];
            $letter = strtoupper($match[2]);
            if (isset($answers[$number]) && $answers[$number] !== $letter) {
                throw new InvalidQuizOutputException('local_exam_parser');
            }
            $answers[$number] = $letter;
        }

        return $answers;
    }

    private function isPageOrStudentDetail(string $line): bool
    {
        return $line === ''
            || preg_match('/^(?:page\s+\d+(?:\s+of\s+\d+)?|\d+\s*\/\s*\d+|name\s*:.*|student\s*(?:name|id)\s*:.*|date\s*:.*|section\s*:.*)\s*$/i', $line) === 1;
    }
}
