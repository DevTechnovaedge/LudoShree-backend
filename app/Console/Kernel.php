<?php

namespace App\Console;

use App\Models\Notification\Notification;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        # Push Notification
        $schedule->call(function () {
            
            $now                =   carbon()->now( 'Asia/Kolkata');
            
            $notifications      =   Notification::select('id', 'user_ids', 'title', 'content', 'sent_type', 'schedule_date_time', 'regular_time')
                                    ->where('sent_type', 'schedule')
                                    ->orWhere('sent_type', 'regular')
                                    ->get();
            
            
            foreach($notifications ?? [] as $notification):
               
               
              if ($notification->sent_type == 'schedule' && carbon()->parse($notification->schedule_date_time)->format('Y-m-d H:i') === $now->format('Y-m-d H:i')) {
                // Process scheduled notifications
                } elseif ($notification->sent_type == 'regular' && carbon()->parse($notification->regular_time)->format('H:i') === $now->format('H:i')) {
                    // Process regular notifications
                } else {
                    return false; // Skip to the next notification
                }
                $users = User::select('id', 'fcm_device_token');
                
                if (is_array($notification->user_ids) && !in_array('all', $notification->user_ids)) {
                   
                    $users->whereIn('id',  $notification->user_ids);
                }
                
                $users = $users->get();

                         foreach($users ?? [] as $user): 
                        $notification_title     = $notification->title;
                         $notification_body     =  $notification->content;
                         $notification_type     =  $notification->sent_type;
                         
                         $fcm_data  =  (object) 
                                [
                                    'title'                 => $notification_title,
                                    'body'                  => $notification_body,
                                    'notification_type'     => $notification_type,
                                    'fcm_device_token'      =>  $user->fcm_device_token,
                                ];
                            

                            if(fcm()->send($fcm_data)):
                                Notification::whereId($notification->id)->increment('sent_count');
                                Notification::whereId($notification->id)->update([ 'is_sent' => 1]);
                            endif;
                        endforeach;
            endforeach;

        })->everyMinute();
        # End Push Notification

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
