<?php

namespace App\Filament\Support;

use App\Enums\ReportStatus;
use App\Models\AnnualReport;
use App\Models\MonthlyReport;
use App\Models\WeeklyProgressReport;
use App\Services\ReportWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Submit, approve, and return actions shared by every report type. Authorisation is enforced again in ReportWorkflow.
 */
class ReportActions
{
    public static function submit(): Action
    {
        return Action::make('submit')
            ->label(fn (WeeklyProgressReport|MonthlyReport|AnnualReport $record): string => $record->status === ReportStatus::Returned ? __('Resubmit for review') : __('Submit for review'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (WeeklyProgressReport|MonthlyReport|AnnualReport $record): bool => OfficeScope::user() !== null
                && app(ReportWorkflow::class)->canSubmit(OfficeScope::user(), $record))
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading(__('Submit this report?'))
            ->modalDescription(fn (WeeklyProgressReport|MonthlyReport|AnnualReport $record): string => self::submissionSummary($record))
            ->modalSubmitActionLabel(__('Submit for review'))
            ->action(function (WeeklyProgressReport|MonthlyReport|AnnualReport $record): void {
                app(ReportWorkflow::class)->submit(OfficeScope::user(), $record);

                Notification::make()
                    ->success()
                    ->title(__('Report submitted'))
                    ->body(ReportWorkflow::reviewerDescription($record->department))
                    ->send();
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->visible(fn (WeeklyProgressReport|MonthlyReport|AnnualReport $record): bool => $record->status === ReportStatus::Submitted
                && (OfficeScope::user()?->canReview($record) ?? false))
            ->modalHeading(__('Approve this report?'))
            ->modalDescription(__('Approved reports become part of the official record and can no longer be changed.'))
            ->schema([
                Textarea::make('comment')
                    ->label(__('Comment for the office (optional)'))
                    ->rows(3),
            ])
            ->modalSubmitActionLabel(__('Approve report'))
            ->action(function (WeeklyProgressReport|MonthlyReport|AnnualReport $record, array $data): void {
                app(ReportWorkflow::class)->approve(OfficeScope::user(), $record, $data['comment'] ?? null);

                Notification::make()->success()->title(__('Report approved'))->send();
            });
    }

    public static function return(): Action
    {
        return Action::make('return')
            ->label(__('Return for correction'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->outlined()
            ->visible(fn (WeeklyProgressReport|MonthlyReport|AnnualReport $record): bool => $record->status === ReportStatus::Submitted
                && (OfficeScope::user()?->canReview($record) ?? false))
            ->modalHeading(__('Return this report for correction'))
            ->modalDescription(__('The office will see your comment at the top of the report and can resubmit it.'))
            ->schema([
                Textarea::make('comment')
                    ->label(__('What needs to be corrected'))
                    ->required()
                    ->rows(4),
            ])
            ->modalSubmitActionLabel(__('Return report'))
            ->action(function (WeeklyProgressReport|MonthlyReport|AnnualReport $record, array $data): void {
                app(ReportWorkflow::class)->return(OfficeScope::user(), $record, $data['comment']);

                Notification::make()->success()->title(__('Report returned to :office', ['office' => $record->department->name]))->send();
            });
    }

    public static function submissionSummary(WeeklyProgressReport|MonthlyReport|AnnualReport $record): string
    {
        $results = match (true) {
            $record instanceof WeeklyProgressReport => trans_choice(':count target result|:count target results', $record->entries()->count()),
            $record instanceof MonthlyReport => trans_choice(':count target result|:count target results', $record->lines()->count()),
            default => __('results calculated from approved monthly reports'),
        };

        return __(':office, :period, with :results.', ['office' => $record->department->name, 'period' => $record->periodLabel(), 'results' => $results])
            .' '.ReportWorkflow::reviewerDescription($record->department)
            .' '.__('You cannot edit the report after submitting unless it is returned.');
    }
}
