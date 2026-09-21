<?php

namespace Tests\Feature\Admin;

use App\Mail\SensitiveDataDeletionMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\SensitiveSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The business's financial identifiers: masked on the page, and deletable only
 * with a link from the owner's mailbox.
 *
 * 🔴 The threat is a STOLEN ADMIN SESSION. Anyone holding one can already change
 * the IBAN, and every bank-transfer customer would then pay an attacker while the
 * receipt email, the order page and the panel all agreed with each other. Masking
 * is shoulder-surfing cover; the emailed confirmation is the real control.
 */
class SensitiveSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'SA9780000145608010008130';

    protected function setUp(): void
    {
        parent::setUp();

        config(['retab.owner_email' => 'owner@retab.test']);

        Setting::set('bank_iban', self::IBAN);
        Setting::set('bank_account', '145608010008130');
        Setting::set('commercial_registration', '1010101010');
        Setting::set('vat_number', '300000000000003');
        // Off by default here: the deletion refuses while it is on, and most of
        // these tests are about the steps AFTER that guard.
        Setting::set('payment_bank_transfer_enabled', '0');
    }

    private function admin(string $email, string $name): User
    {
        return User::firstWhere('email', $email) ?? User::forceCreate([
            'name' => $name, 'email' => $email, 'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    private function owner(): User
    {
        return $this->admin('owner@retab.test', 'Owner');
    }

    private function otherAdmin(): User
    {
        return $this->admin('other@retab.test', 'Admin');
    }

    // ---- Masking -----------------------------------------------------------

    public function test_the_settings_page_never_ships_the_real_values(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/settings')
            ->assertInertia(fn ($page) => $page
                ->where('settings.bank_iban', '••••••••••••••••••••8130')
                ->where('settings.vat_number', '•••••••••••0003'))
            // The strongest form of this assertion: the real value is nowhere in
            // the response at all, not merely absent from one prop.
            ->assertDontSee(self::IBAN);
    }

    /** A short value gives away too much from its last four, so it is hidden whole. */
    public function test_a_short_value_is_masked_entirely(): void
    {
        $this->assertSame('••••••', SensitiveSettings::mask('123456'));
        $this->assertSame('', SensitiveSettings::mask(null));
    }

    /**
     * 🔴 THE ONE THAT MATTERS MOST. The settings form posts every field it
     * rendered, so saving an unrelated change while the IBAN is masked must not
     * write a row of dots over it. If this breaks, every bank-transfer customer
     * is told to pay into `••••8130`, and nothing errors anywhere.
     */
    public function test_saving_the_form_without_revealing_does_not_destroy_the_iban(): void
    {
        $this->actingAs($this->owner())->put('/admin/settings', [
            'shipping_flat_fee' => 30,
            'bank_iban' => '••••••••••••••••••••8130',
            'bank_account' => '•••••••••••8130',
            'commercial_registration' => '••••••1010',
            'vat_number' => '•••••••••••0003',
            'payment_card_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
        $this->assertSame('145608010008130', Setting::get('bank_account'));
        $this->assertSame('30', Setting::get('shipping_flat_fee'));
    }

    /** A genuine edit still goes through. */
    public function test_a_revealed_and_edited_value_is_saved(): void
    {
        $this->actingAs($this->owner())->put('/admin/settings', [
            'shipping_flat_fee' => 25,
            'bank_iban' => 'SA0380000000608010167519',
            'payment_card_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('SA0380000000608010167519', Setting::get('bank_iban'));
    }

    // ---- Revealing ----------------------------------------------------------

    public function test_an_admin_can_reveal_one_value(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/sensitive/reveal?key=bank_iban')
            ->assertOk()
            ->assertJson(['value' => self::IBAN]);
    }

    /** Only the listed keys. Anything else is not this endpoint's business. */
    public function test_revealing_a_non_sensitive_key_is_refused(): void
    {
        $this->actingAs($this->owner())->get('/admin/sensitive/reveal?key=bank_name')->assertNotFound();
    }

    public function test_an_editor_without_settings_edit_cannot_reveal(): void
    {
        $editor = User::forceCreate([
            'name' => 'E', 'email' => 'e@retab.test', 'password' => bcrypt('x'),
            'role' => 'editor', 'permissions' => ['settings' => ['view' => true, 'edit' => false]],
        ]);

        $this->actingAs($editor)->get('/admin/sensitive/reveal?key=bank_iban')->assertForbidden();
    }

    // ---- Requesting the deletion ---------------------------------------------

    public function test_only_the_owner_can_request_the_deletion(): void
    {
        Mail::fake();

        $this->actingAs($this->otherAdmin())->post('/admin/sensitive/delete')->assertForbidden();

        Mail::assertNothingSent();
        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
    }

    public function test_the_owner_gets_an_email_and_nothing_is_deleted_yet(): void
    {
        Mail::fake();

        $this->actingAs($this->owner())->post('/admin/sensitive/delete')->assertRedirect();

        Mail::assertSent(SensitiveDataDeletionMail::class);
        // 🔑 The whole point of step one: asking does not delete.
        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
    }

    /**
     * 🔴 Deleting the IBAN while bank transfer is still offered leaves every new
     * order with nowhere to pay, and nothing errors. Refuse up front.
     */
    public function test_it_refuses_while_bank_transfer_is_still_enabled(): void
    {
        Mail::fake();
        Setting::set('payment_bank_transfer_enabled', '1');

        $this->actingAs($this->owner())->post('/admin/sensitive/delete')->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_it_refuses_when_there_is_nothing_to_delete(): void
    {
        Mail::fake();
        SensitiveSettings::purge();

        $this->actingAs($this->owner())->post('/admin/sensitive/delete')->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    // ---- Confirming ------------------------------------------------------------

    /** Drives the real flow: request, capture the emailed link, open it. */
    private function requestAndCaptureLink(): string
    {
        Mail::fake();
        $this->actingAs($this->owner())->post('/admin/sensitive/delete');

        $url = null;
        Mail::assertSent(SensitiveDataDeletionMail::class, function ($mail) use (&$url) {
            $url = (new \ReflectionProperty($mail, 'confirmUrl'))->getValue($mail);

            return true;
        });

        return (string) $url;
    }

    public function test_opening_the_emailed_link_deletes_them(): void
    {
        $url = $this->requestAndCaptureLink();

        $this->actingAs($this->owner())->get($url)->assertRedirect(route('admin.settings.edit'));

        foreach (SensitiveSettings::KEYS as $key) {
            $this->assertSame('', Setting::get($key), "{$key} should have been cleared");
        }
    }

    /** 🔑 Single use. A signed URL alone stays replayable for its whole lifetime. */
    public function test_the_link_cannot_be_used_twice(): void
    {
        $url = $this->requestAndCaptureLink();
        $owner = $this->owner();

        $this->actingAs($owner)->get($url);
        Setting::set('bank_iban', self::IBAN); // re-entered afterwards

        $this->actingAs($owner)->get($url)->assertSessionHas('error');

        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
    }

    /** 🔴 A tampered or unsigned link is not a link. */
    public function test_an_unsigned_link_is_refused(): void
    {
        $this->requestAndCaptureLink();

        $this->actingAs($this->owner())
            ->get(route('admin.sensitive.confirm', ['token' => 'made-up']))
            ->assertForbidden();

        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
    }

    /**
     * 🔴 The email is a SECOND factor, not the only one. A forwarded link must not
     * be enough on its own.
     */
    public function test_another_admin_holding_the_link_cannot_use_it(): void
    {
        $url = $this->requestAndCaptureLink();

        $this->actingAs($this->otherAdmin())->get($url)->assertForbidden();

        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
    }

    public function test_an_expired_signature_is_refused(): void
    {
        $url = URL::temporarySignedRoute('admin.sensitive.confirm', now()->subMinute(), ['token' => 'x']);

        $this->actingAs($this->owner())->get($url)->assertForbidden();

        $this->assertSame(self::IBAN, Setting::get('bank_iban'));
    }
}
