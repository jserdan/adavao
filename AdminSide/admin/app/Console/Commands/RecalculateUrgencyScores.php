<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Report;

class RecalculateUrgencyScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:recalculate-urgency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculates urgency scores for all reports based on new crime type logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting urgency score recalculation...');

        $count = 0;
        $updated = 0;

        // Keep this aligned with ReportController::setPriorityFlags() urgency tiers.
        $CRITICAL_CRIMES = [
            'Murder', 'Homicide', 'Rape', 'Sexual Assault',
            'Kidnapping', 'Abduction', 'Stabbing', 'Shooting',
            'Human Trafficking', 'Child Abuse', 'Exploitation',
        ];
        $HIGH_PRIORITY = [
            'Robbery', 'Holdup', 'Physical Assault', 'Physical Injury',
            'Domestic Violence', 'Missing Person', 'Harassment',
            'Arson', 'Fire Emergency', 'Drug', 'Illegal Firearms',
            'Weapons', 'Sextortion', 'Sexual Harassment',
        ];
        $MEDIUM_PRIORITY = [
            'Theft', 'Pickpocketing', 'Burglary', 'Break-in',
            'Carnapping', 'Motornapping', 'Motorcycle Theft', 'Vehicle Theft',
            'Threats', 'Intimidation', 'Fraud', 'Scam', 'Phishing',
            'Cybercrime', 'Cyberbullying', 'Hacking', 'Identity Theft',
            'Vandalism', 'Trespassing', 'Road Accident',
            'Online Threats', 'Medical Emergency',
        ];

        Report::query()
            ->select(['report_id', 'report_type'])
            ->withCount('media')
            ->orderBy('report_id')
            ->chunkById(200, function ($reports) use (&$count, &$updated, $CRITICAL_CRIMES, $HIGH_PRIORITY, $MEDIUM_PRIORITY) {
                foreach ($reports as $report) {
                    $count++;

                    $crimeTypes = $this->normalizeCrimeTypes($report->report_type);
                    if (empty($crimeTypes)) {
                        continue;
                    }

                    $score = 30; // Base LOW
                    $hasCritical = false;
                    $hasHigh = false;
                    $hasMedium = false;

                    foreach ($crimeTypes as $crime) {
                        $matched = false;
                        foreach ($CRITICAL_CRIMES as $c) {
                            if (stripos($crime, $c) !== false) {
                                $hasCritical = true;
                                $matched = true;
                                break;
                            }
                        }

                        if (!$matched) {
                            foreach ($HIGH_PRIORITY as $c) {
                                if (stripos($crime, $c) !== false) {
                                    $hasHigh = true;
                                    $matched = true;
                                    break;
                                }
                            }
                        }

                        if (!$matched) {
                            foreach ($MEDIUM_PRIORITY as $c) {
                                if (stripos($crime, $c) !== false) {
                                    $hasMedium = true;
                                    break;
                                }
                            }
                        }
                    }

                    if ($hasCritical) {
                        $score = 100;
                    } elseif ($hasHigh) {
                        $score = 75;
                    } elseif ($hasMedium) {
                        $score = 50;
                    }

                    // Match admin scoring behavior: +10 when report has evidence/media.
                    if ((int) $report->media_count > 0) {
                        $score = min(100, $score + 10);
                    }

                    DB::table('reports')
                        ->where('report_id', $report->report_id)
                        ->update(['urgency_score' => $score]);

                    $updated++;

                    if ($count % 100 === 0) {
                        $this->info("Processed {$count} reports...");
                    }
                }
            }, 'report_id', 'report_id');

        $this->info("✅ Completed! Updated {$updated} reports.");
    }

    private function normalizeCrimeTypes($rawType): array
    {
        if (is_array($rawType)) {
            return array_values(array_filter(array_map(function ($item) {
                return is_string($item) ? trim($item) : trim((string) $item);
            }, $rawType)));
        }

        if (is_string($rawType)) {
            $decoded = json_decode($rawType, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map(function ($item) {
                        return is_string($item) ? trim($item) : trim((string) $item);
                    }, $decoded)));
                }

                if (!empty($decoded)) {
                    return [trim((string) $decoded)];
                }
            }

            if (trim($rawType) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $rawType))));
            }
        }

        return [];
    }

    private function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
