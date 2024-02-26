<?php

namespace App\Filament\Faculty\Resources\ProofreadingRequestResource\Pages;

use App\Filament\Faculty\Resources\ProofreadingRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Models\ProofreadingRequest;
use App\Models\User;
use App\Models\UserTeam;
use App\Models\ProofreadingRequestStatus;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;


class ViewProofreadingRequest extends ViewRecord
{
    protected static string $resource = ProofreadingRequestResource::class;
    protected function getFooterWidgets(): array
    {
        return [
            ProofreadingRequestResource\Widgets\ProofreadingRequestStatusHistory::class,
            ProofreadingRequestResource\Widgets\TeamMembers::class,
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if(!empty($this->record->latestStatus)){
            $data['proofreading_status'] = $this->record->latestStatus->status;
            $data['feedback'] = $this->record->latestStatus->feedback;
        }
        
        return $data;
    }
    protected function getHeaderActions(): array
    {
        $roles = User::join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('users.id', Auth()->id())
        ->pluck('roles.name')
        ->toArray();
    
        $status = $this->record->latestStatus->status;

        return [
                    //professor endorse
                    Action::make('endorse')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Select::make('executive_director_id')
                        ->relationship('getApprover', 'email')
                        ->required(),
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons(),
                    ])
                    ->action(function (array $data) {
                        $usersTeam = UserTeam::where('team_id', $this->record->projectSubmission->team_id)->pluck('user_id')->toArray();
                        $users =  User::whereIn('id', $usersTeam)->where('id', $this->record->executive_director_id)->get();

                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'endorsed',
                            'type' => 'professor',
                            'feedback' => $data['feedback'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'endorsed',
                            'executive_director_id' => $data['executive_director_id'],
                        ]),
                        Notification::make()
                            ->title(auth()->user()->email.' endorsed a project submission.')
                            ->body($this->record->title.' has been endorsed.')
                            ->sendToDatabase($users)
                    ];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'pending')||($this->record->latestStatus->status == 'returned for endorsement'))&&($request->endorser_id == auth()->user()->id)){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),
                    //endorse return
                    Action::make('return')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons()
                    ])
                    ->action(function (array $data) {
                        $usersTeam = UserTeam::where('team_id', $this->record->projectSubmission->team_id)->pluck('user_id')->toArray();
                        $users =  User::whereIn('id', $usersTeam)->where('id', $this->record->executive_director_id)->get();
                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'returned for endorsement',
                            'type' => 'professor',
                            'feedback' => $data['feedback'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'returned for endorsement',
                        ]),
                        Notification::make()
                        ->title(auth()->user()->email.' returned a project submission.')
                        ->body($this->record->title.' has been returned for endorsement.')
                        ->sendToDatabase($users)
                    ];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'pending')||($this->record->latestStatus->status == 'returned for endorsement')||($this->record->latestStatus->status == 'endorsed'))&&($request->endorser_id == auth()->user()->id)){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),

                    //xd approve
                    Action::make('approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons()
                    ])
                    ->action(function (array $data) {
                        $usersTeam = UserTeam::where('team_id', $this->record->projectSubmission->team_id)->pluck('user_id')->toArray();
                        $users =  User::whereIn('id', $usersTeam)->get();
                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'approved',
                            'type' => 'executive director',
                            'feedback' => $data['feedback'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'approved',
                        ]),
                        Notification::make()
                        ->title(auth()->user()->email.' approved a project submission.')
                        ->body($this->record->title.' has been approved.')
                        ->sendToDatabase($users),
                    ];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'endorsed')||($this->record->latestStatus->status == 'returned for approval'))&&($request->executive_director_id==auth()->user()->id)){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),
                    //xd return
                    Action::make('return')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons()
                    ])
                    ->action(function (array $data) {

                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'returned for approval',
                            'type' => 'executive director',
                            'feedback' => $data['feedback'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'returned for approval',
                        ])];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'endorsed')||($this->record->latestStatus->status == 'returned for approval')||($this->record->latestStatus->status == 'approved'))&&($request->executive_director_id==auth()->user()->id)){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),
                    //ec head assigm
                    Action::make('assign')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Select::make('proofreader_id')
                        ->relationship('getProofreaders', 'email')
                        ->required(),
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons(),
                    ])
                    ->action(function (array $data) {
                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'assigned',
                            'type' => 'english cluster head',
                            'feedback' => $data['feedback'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'assigned',
                            'proofreader_id' => $data['proofreader_id'],
                        ])];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'approved')||($this->record->latestStatus->status == 'returned for assignment'))&&(in_array('English Cluster Head', $this->record->getRole()))){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),
                    //ec head return
                    Action::make('return')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->form([
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons()
                    ])
                    ->action(function (array $data) {
                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'returned for assignment',
                            'type' => 'english cluster head',
                            'feedback' => $data['feedback'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'returned for assignment',
                        ])];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'approved')||($this->record->latestStatus->status == 'returned for assigment')||($this->record->latestStatus->status == 'assigned'))&&(in_array('English Cluster Head', $this->record->getRole()))){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),
                    //proofreader return
                    Action::make('Complete')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        FileUpload::make('attachments')
                        ->multiple()
                        ->storeFileNamesIn('attachments_names')
                        ->openable()
                        ->downloadable()
                        ->previewable(true)
                        ->directory('project_files')
                        ->acceptedFileTypes(['application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/pdf']),
                        RichEditor::make('feedback')
                        ->maxLength('255')
                        ->disableAllToolbarButtons(),
                    ])
                    ->action(function (array $data) {
                        return [ProofreadingRequestStatus::create([
                            'proofreading_request_id' => $this->record->id,
                            'user_id' => auth()->user()->id,
                            'status' => 'completed',
                            'type' => 'proofreader',
                            'feedback' => $data['feedback'],
                            'attachments' => $data['attachments'],
                        ]),
                        ProofreadingRequest::where('id',$this->record->id)->update([
                            'status' => 'completed',
                        ])];
                    })
                    ->visible(function (ProofreadingRequest $request): bool {
                        if((($this->record->latestStatus->status == 'assigned'))&&($request->proofreader_id==auth()->id())){
                            $visible = true;
                        }
                        else{
                            $visible = false;
                        }
                        return $visible;
                    }),

        ];
    }
    
}
