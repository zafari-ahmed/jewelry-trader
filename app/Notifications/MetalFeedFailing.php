<?php

namespace App\Notifications;

use App\Models\MetalRateFetch;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The metals feed has failed enough times in a row to be worth telling
 * somebody about.
 *
 * Deliberately plain: what is wrong, what the system is doing about it, and
 * where to look. Pricing has not stopped unless the business chose "hold",
 * and the message says which.
 */
class MetalFeedFailing extends Notification
{
    use Queueable;

    public function __construct(private int $failures) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $behaviour = (string) Setting::get('pricing.live_rates_failure_behaviour', 'base');
        $last = MetalRateFetch::lastSuccessful();

        $consequence = match ($behaviour) {
            'hold' => 'Metal pricing is on hold: suggestions will report the metal rate as missing until the feed recovers.',
            'last_known' => 'Pricing is using the last rates the feed returned'
                .($last ? ', from '.$last->created_at->diffForHumans().'.' : '.'),
            default => 'Pricing has fallen back to your own base rate table, so the shop is unaffected.',
        };

        return (new MailMessage)
            ->subject('Jewelry Trader — the live metals feed is not responding')
            ->line("The metals feed has failed {$this->failures} times in a row.")
            ->line($consequence)
            ->action('Check the feed settings', route('admin.settings.pricing'))
            ->line('You will not get another message about this run of failures.');
    }
}
