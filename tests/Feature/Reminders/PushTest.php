<?php

namespace Tests\Feature\Reminders;

use App\Models\Reminder;
use App\Models\ReminderRecipient;
use App\Models\User;
use App\Notifications\ReminderDue;
use App\Services\Reminders\ReminderEngine;
use App\Services\Reminders\ReminderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    private function subscribed(): User
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push.example.com/send/abc123', 'p256dh-key', 'auth-token');

        return $user;
    }

    private function recipientFor(User $user, array $attributes = []): ReminderRecipient
    {
        $reminder = Reminder::factory()->create($attributes + ['created_by' => $user->id, 'due_at' => now()->addMinutes(30)]);
        app(ReminderEngine::class)->sync($reminder);

        return $reminder->recipients()->firstOrFail();
    }

    // ------------------------------------------------------------ subscribing

    public function test_guests_cannot_subscribe(): void
    {
        $this->postJson('/devices/subscribe', [])->assertUnauthorized();
    }

    public function test_a_device_can_subscribe_and_unsubscribe(): void
    {
        $user = User::factory()->create();
        $payload = ['endpoint' => 'https://push.example.com/send/device-1', 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($user)->postJson('/devices/subscribe', $payload)->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->postJson('/devices/subscribe', $payload)->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count(), 'the same device is not stored twice');

        $this->actingAs($user)->postJson('/devices/unsubscribe', ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_a_subscription_needs_an_endpoint_and_keys(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/devices/subscribe', ['endpoint' => 'not-a-url'])->assertUnprocessable();
    }

    public function test_the_test_notification_needs_a_subscribed_device(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/devices/test')->assertStatus(422);
    }

    public function test_the_notifications_page_has_the_switch_and_the_public_key(): void
    {
        config(['webpush.vapid.public_key' => 'public-key-for-test']);

        $this->actingAs(User::factory()->create())->get('/account/notifications')
            ->assertOk()
            ->assertSee('Turn on notifications')
            ->assertSee('<meta name="vapid-public-key" content="public-key-for-test">', false);
    }

    // ------------------------------------------------------------ the notification

    public function test_it_only_uses_web_push_for_users_with_a_subscribed_device(): void
    {
        $with = $this->subscribed();
        $without = User::factory()->create();

        $this->assertSame([WebPushChannel::class], (new ReminderDue($this->recipientFor($with)))->via($with));
        $this->assertSame([], (new ReminderDue($this->recipientFor($without)))->via($without));
    }

    public function test_the_notification_has_a_title_body_and_two_buttons(): void
    {
        $user = $this->subscribed();
        $recipient = $this->recipientFor($user, ['title' => 'Send the report']);

        $payload = (new ReminderDue($recipient))->toWebPush($user, new ReminderDue($recipient))->toArray();

        $this->assertSame('Send the report', $payload['title']);
        $this->assertStringContainsString('Due', $payload['body']);
        $this->assertSame('reminder-'.$recipient->reminder_id, $payload['tag']);
        $this->assertSame(['done', 'snooze'], array_column($payload['actions'], 'action'));
        $this->assertTrue(URL::hasValidSignature(request()->create($payload['data']['doneUrl'])));
        $this->assertTrue(URL::hasValidSignature(request()->create($payload['data']['snoozeUrl'])));
    }

    public function test_the_notifier_sends_through_the_notification_system(): void
    {
        Notification::fake();
        config(['webpush.vapid.public_key' => 'public-key-for-test']);
        $user = $this->subscribed();

        app(ReminderNotifier::class)->send($this->recipientFor($user));

        Notification::assertSentTo($user, ReminderDue::class);
    }

    public function test_the_notifier_does_nothing_without_vapid_keys(): void
    {
        Notification::fake();
        config(['webpush.vapid.public_key' => null]);

        app(ReminderNotifier::class)->send($this->recipientFor($this->subscribed()));

        Notification::assertNothingSent();
    }

    public function test_a_failing_push_service_does_not_break_sending(): void
    {
        config(['webpush.vapid.public_key' => 'public-key-for-test']);
        $user = $this->subscribed();
        $recipient = $this->recipientFor($user);

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('push service down'));
        Log::shouldReceive('warning')->once();

        app(ReminderNotifier::class)->send($recipient);

        $this->assertTrue(true);
    }

    // ------------------------------------------------------------ the signed buttons

    public function test_the_done_button_works_without_logging_in(): void
    {
        $recipient = $this->recipientFor(User::factory()->create());
        $url = URL::temporarySignedRoute('push.done', now()->addHour(), ['recipient' => $recipient->id]);

        $this->post($url)->assertNoContent();

        $this->assertNotNull($recipient->fresh()->completed_at);
        $this->assertNotNull($recipient->reminder->fresh()->completed_at);
    }

    public function test_the_snooze_button_pushes_the_reminder_back(): void
    {
        $recipient = $this->recipientFor(User::factory()->create());
        $url = URL::temporarySignedRoute('push.snooze', now()->addHour(), ['recipient' => $recipient->id]);

        $this->post($url)->assertNoContent();

        $this->assertNotNull($recipient->fresh()->snoozed_until);
        $this->assertNull($recipient->fresh()->completed_at);
    }

    public function test_unsigned_tampered_and_expired_links_are_refused(): void
    {
        $mine = $this->recipientFor(User::factory()->create());
        $other = $this->recipientFor(User::factory()->create());

        $this->post(route('push.done', $mine))->assertForbidden();

        $signed = URL::temporarySignedRoute('push.done', now()->addHour(), ['recipient' => $mine->id]);
        $this->post(str_replace('/push/'.$mine->id.'/', '/push/'.$other->id.'/', $signed))->assertForbidden();

        $expired = URL::temporarySignedRoute('push.done', now()->subMinute(), ['recipient' => $mine->id]);
        $this->post($expired)->assertForbidden();

        $this->assertNull($mine->fresh()->completed_at);
        $this->assertNull($other->fresh()->completed_at);
    }

    public function test_pressing_a_button_twice_or_after_the_reminder_is_finished_is_harmless(): void
    {
        $recipient = $this->recipientFor(User::factory()->create());
        $url = URL::temporarySignedRoute('push.done', now()->addHour(), ['recipient' => $recipient->id]);

        $this->post($url)->assertNoContent();
        $this->post($url)->assertNoContent();

        $this->assertSame(1, $recipient->reminder->events()->where('type', 'done')->count());
    }
}
