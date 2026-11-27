<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function professor(Request $request, Quiz $quiz)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'class_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $report = $this->analytics->professorReport(Auth::user(), $quiz, $filters);

        return view('professor.analytics.show', $report);
    }

    public function export(Request $request, Quiz $quiz): StreamedResponse
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'class_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $report = $this->analytics->professorReport(Auth::user(), $quiz, $filters);
        $filename = 'quiz-'.$quiz->id.'-analytics.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Metric', 'Value', 'Sample size', 'Suppressed']);
            fputcsv($output, [
                'Completion rate (completed participants / joined participants)',
                $report['participation']['completion_rate'] === null ? '' : $report['participation']['completion_rate'].'%',
                $report['participation']['joined'],
                'no',
            ]);
            fputcsv($output, ['Completed / joined participants', $report['participation']['completed'].'/'.$report['participation']['joined'], $report['participation']['joined'], 'no']);
            fputcsv($output, ['Mean score', $report['score']['mean'] === null ? '' : $report['score']['mean'].'%', $report['participation']['completed'], 'no']);
            fputcsv($output, ['Median score', $report['score']['median'] === null ? '' : $report['score']['median'].'%', $report['participation']['completed'], 'no']);
            fputcsv($output, ['Median response time', $report['median_response_seconds'] === null ? '' : $report['median_response_seconds'].' seconds', '', 'no']);
            fputcsv($output, []);
            fputcsv($output, ['Score distribution', 'Attempts', 'Sample size', 'Suppressed']);
            foreach ($report['score_distribution'] ?? ['0–49%' => null, '50–69%' => null, '70–84%' => null, '85–100%' => null] as $bucket => $count) {
                fputcsv($output, [
                    $bucket,
                    $count ?? '',
                    $report['participation']['completed'],
                    $report['score_distribution_suppressed'] ? 'yes' : 'no',
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Question', 'Accuracy', 'Skip / timeout', 'Answer-change rate', 'Median time (seconds)', 'Most chosen distractor', 'Respondents', 'Suppressed']);
            foreach ($report['questions'] as $question) {
                fputcsv($output, [
                    $question['snippet'],
                    $question['accuracy'] === null ? '' : $question['accuracy'].'%',
                    $question['skip_timeout'] === null ? '' : $question['skip_timeout'].'%',
                    $question['answer_change_rate'] === null ? '' : $question['answer_change_rate'].'%',
                    $question['median_seconds'] ?? '',
                    $question['distractor'] ?? '',
                    $question['suppressed'] ? '' : $question['respondents'],
                    $question['suppressed'] ? 'yes' : 'no',
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Language', 'Topic', 'Accuracy', 'Students', 'Suppressed']);
            foreach ($report['topic_mastery'] as $topic) {
                fputcsv($output, [
                    $topic['language'],
                    $topic['topic'],
                    $topic['accuracy'] === null ? '' : $topic['accuracy'].'%',
                    $topic['suppressed'] ? '' : $topic['student_count'],
                    $topic['suppressed'] ? 'yes' : 'no',
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function student()
    {
        return view('student.progress', [
            'progress' => $this->analytics->studentProgress(Auth::user()),
        ]);
    }
}
