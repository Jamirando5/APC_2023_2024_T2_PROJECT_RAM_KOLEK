<?php

namespace App\Filament\Student\Resources\ProofreadingRequestResource\Pages;

use App\Filament\Student\Resources\ProofreadingRequestResource;
use App\Models\ProofreadingRequestStatus;
use App\Models\ProofreadingRequest;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\User;
use App\Models\ProjectSubmission;

class CreateProofreadingRequest extends CreateRecord
{
    protected static string $resource = ProofreadingRequestResource::class;
    protected function afterCreate(): void
    {
        ProofreadingRequest::where('id',$this->record->id)->update([
            'owner_id' => auth()->user()->id,
            'endorser_id' => $this->record->projectSubmission->professor_id,
        ]);
        ProofreadingRequestStatus::create([
            'proofreading_request_id' => $this->record->id,
            'user_id' => $this->record->projectSubmission->professor_id,
            'status' => 'pending',
            'type' => 'professor',
        ]);
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->id;
        return $data;
    }
    protected function getCreatedNotification(): ?Notification
    {
        $recipient = User::where('id', $this->record->endorser_id)->get();
        return Notification::make()
            ->title(auth()->user()->email.' created a proofreading request.')
            ->body($this->record->projectSubmission->title.' proofreading request created.')
            ->sendToDatabase($recipient);            
    }
}
