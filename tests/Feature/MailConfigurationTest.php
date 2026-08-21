<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Xolution's SMTP credentials arrived under XOL_ names rather than Laravel's
 * MAIL_ ones (SPEC §7, §12), so config/mail.php maps them.
 *
 * Worth a test because the failure mode is silent: a mistyped key name reads
 * as null, the mailer quietly falls back to a local address on port 2525, and
 * the first anyone knows is a quote that never arrived.
 *
 * Every one of these keys is blanked before each case and restored after, so
 * nothing here ever reads the real environment. That is not tidiness: an
 * assertion that fails prints the value it found, and the real value of one of
 * these is a password. It has to be impossible for a broken test to put it on
 * screen.
 *
 * Blanking means all three of $_ENV, $_SERVER and putenv. Laravel's env()
 * reads them in that order and stops at the first hit, so clearing only the
 * last of them leaves the real value in play - which is exactly the mistake
 * this comment exists to stop the next person repeating.
 */
class MailConfigurationTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const KEYS = [
        'XOL_SMTP',
        'XOL_PORT',
        'XOL_FROM',
        'XOL_USER',
        'XOL_PASS',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_FROM_ADDRESS',
    ];

    /**
     * @var array<string, array{env: mixed, server: mixed, putenv: string|false}>
     */
    private array $originals = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::KEYS as $key) {
            $this->originals[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'putenv' => getenv($key),
            ];

            $this->forget($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $key => $original) {
            $this->forget($key);

            if ($original['env'] !== null) {
                $_ENV[$key] = $original['env'];
            }

            if ($original['server'] !== null) {
                $_SERVER[$key] = $original['server'];
            }

            if ($original['putenv'] !== false) {
                putenv("{$key}={$original['putenv']}");
            }
        }

        parent::tearDown();
    }

    /**
     * The guard on everything below, and the reason the blanking above exists.
     *
     * Deliberately assertTrue with a message rather than assertNull: a failing
     * assertNull prints the value it found, and the value it would find here
     * is a live SMTP password. This says what went wrong without saying what
     * it saw.
     */
    public function test_the_real_environment_is_out_of_reach()
    {
        $config = $this->mailConfig();

        $this->assertTrue(
            $config['mailers']['smtp']['password'] === null,
            'A real credential was visible to this test. Blanking is not working.',
        );
        $this->assertTrue(
            $config['mailers']['smtp']['host'] === '127.0.0.1',
            'The real relay host was visible to this test. Blanking is not working.',
        );
    }

    public function test_the_relay_is_read_from_xolutions_own_keys()
    {
        $this->set('XOL_SMTP', 'smtp.example.test');
        $this->set('XOL_PASS', 'not-the-real-one');

        $config = $this->mailConfig();

        $this->assertSame('smtp.example.test', $config['mailers']['smtp']['host']);
        $this->assertSame('not-the-real-one', $config['mailers']['smtp']['password']);
    }

    /**
     * A relay of this kind usually authenticates as the address it sends from.
     */
    public function test_the_username_defaults_to_the_address_it_sends_from()
    {
        $this->set('XOL_SMTP', 'smtp.example.test');
        $this->set('XOL_FROM', 'quotes@example.test');

        $config = $this->mailConfig();

        $this->assertSame('quotes@example.test', $config['mailers']['smtp']['username']);
        $this->assertSame('quotes@example.test', $config['from']['address']);
    }

    public function test_a_separate_account_can_be_named_when_it_is_not_that_address()
    {
        $this->set('XOL_SMTP', 'smtp.example.test');
        $this->set('XOL_FROM', 'quotes@example.test');
        $this->set('XOL_USER', 'relay-account');

        $config = $this->mailConfig();

        $this->assertSame('relay-account', $config['mailers']['smtp']['username']);
        // The address a customer replies to is unaffected by which account was
        // used to hand the message over.
        $this->assertSame('quotes@example.test', $config['from']['address']);
    }

    public function test_submission_over_starttls_is_the_default_port()
    {
        $this->set('XOL_SMTP', 'smtp.example.test');

        $this->assertSame(587, $this->mailConfig()['mailers']['smtp']['port']);
    }

    public function test_the_port_can_be_told_when_the_relay_uses_another()
    {
        $this->set('XOL_SMTP', 'smtp.example.test');
        $this->set('XOL_PORT', '465');

        $this->assertSame('465', $this->mailConfig()['mailers']['smtp']['port']);
    }

    /**
     * MAIL_PORT carries whatever a local mail catcher listens on. Pairing that
     * with Xolution's relay would fail in a way that reads as a credentials
     * problem, so naming the host means naming its port.
     */
    public function test_a_local_catchers_port_is_never_paired_with_the_relay()
    {
        $this->set('XOL_SMTP', 'smtp.example.test');
        $this->set('MAIL_PORT', '2525');

        $this->assertSame(587, $this->mailConfig()['mailers']['smtp']['port']);
    }

    /**
     * A machine configured the ordinary Laravel way still works, which is what
     * keeps this from being a fork of the framework's own configuration.
     */
    public function test_the_standard_keys_still_apply_when_xolutions_are_absent()
    {
        $this->set('MAIL_HOST', '127.0.0.1');
        $this->set('MAIL_PORT', '2525');
        $this->set('MAIL_FROM_ADDRESS', 'someone@example.test');

        $config = $this->mailConfig();

        $this->assertSame('127.0.0.1', $config['mailers']['smtp']['host']);
        $this->assertSame('2525', $config['mailers']['smtp']['port']);
        $this->assertSame('someone@example.test', $config['from']['address']);
    }

    private function set(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    private function forget(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    /**
     * Re-evaluated rather than read from the container: the container's copy
     * was built once, from the real environment, before any of this ran.
     *
     * @return array<string, mixed>
     */
    private function mailConfig(): array
    {
        return require base_path('config/mail.php');
    }
}
