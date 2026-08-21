<?php

namespace Tests\Feature;

use Tests\Concerns\HidesTheRealEnvironment;
use Tests\TestCase;

/**
 * The PDF container is behind basic auth (SPEC §7), and its credentials are
 * read under the names Gotenberg itself uses, so the same pair goes into the
 * container's environment on Render and into this application's unchanged.
 *
 * Worth a test because it has already gone wrong once: the credentials were
 * set as GOTENBERG_API_BASIC_AUTH_* while this read GOTENBERG_USERNAME, so
 * the application sent nothing and every download came back 401.
 *
 * The keys are hidden for the duration - one of them is a live password, and a
 * failing assertion prints what it found. See HidesTheRealEnvironment.
 */
class GotenbergConfigurationTest extends TestCase
{
    use HidesTheRealEnvironment;

    /**
     * @var list<string>
     */
    private const KEYS = [
        'GOTENBERG_URL',
        'GOTENBERG_API_BASIC_AUTH_USERNAME',
        'GOTENBERG_API_BASIC_AUTH_PASSWORD',
        'GOTENBERG_USERNAME',
        'GOTENBERG_PASSWORD',
        'GOTENBERG_TIMEOUT',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->hideEnvironmentKeys(self::KEYS);
    }

    protected function tearDown(): void
    {
        $this->restoreTheRealEnvironment();

        parent::tearDown();
    }

    /**
     * The guard on everything below, and the reason the hiding exists.
     *
     * assertTrue with a message rather than assertNull: a failing assertNull
     * prints the value it found, and the value it would find here is a live
     * password.
     */
    public function test_the_real_environment_is_out_of_reach()
    {
        $config = $this->gotenbergConfig();

        $this->assertTrue(
            $config['password'] === null,
            'A real credential was visible to this test. Hiding is not working.',
        );
        $this->assertTrue(
            $config['url'] === null,
            'The real container address was visible to this test. Hiding is not working.',
        );
    }

    public function test_the_credentials_are_read_under_gotenbergs_own_names()
    {
        $this->setEnvironmentKey('GOTENBERG_API_BASIC_AUTH_USERNAME', 'a-long-user');
        $this->setEnvironmentKey('GOTENBERG_API_BASIC_AUTH_PASSWORD', 'a-longer-password');

        $config = $this->gotenbergConfig();

        $this->assertSame('a-long-user', $config['username']);
        $this->assertSame('a-longer-password', $config['password']);
    }

    /**
     * Anything already configured the shorter way keeps working, so switching
     * the container to basic auth does not mean editing two places at once.
     */
    public function test_the_shorter_names_still_apply()
    {
        $this->setEnvironmentKey('GOTENBERG_USERNAME', 'older-user');
        $this->setEnvironmentKey('GOTENBERG_PASSWORD', 'older-password');

        $config = $this->gotenbergConfig();

        $this->assertSame('older-user', $config['username']);
        $this->assertSame('older-password', $config['password']);
    }

    public function test_gotenbergs_own_names_win_when_both_are_set()
    {
        $this->setEnvironmentKey('GOTENBERG_API_BASIC_AUTH_USERNAME', 'current-user');
        $this->setEnvironmentKey('GOTENBERG_USERNAME', 'older-user');

        $this->assertSame('current-user', $this->gotenbergConfig()['username']);
    }

    /**
     * A container that answers to anyone is a state this has to survive, not
     * least because that is what it was until recently.
     */
    public function test_no_credentials_at_all_is_a_supported_state()
    {
        $this->setEnvironmentKey('GOTENBERG_URL', 'https://pdf.example.test');

        $config = $this->gotenbergConfig();

        $this->assertSame('https://pdf.example.test', $config['url']);
        $this->assertNull($config['username']);
        $this->assertNull($config['password']);
    }

    /**
     * @return array<string, mixed>
     */
    private function gotenbergConfig(): array
    {
        return $this->freshConfig('services')['gotenberg'];
    }
}
