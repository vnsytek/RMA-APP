<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Production runs under a sub-folder (https://app.vnsytek.com/rma, nginx rewrites /rma/* to
 * /rma/index.php). Every redirect must keep that prefix: a bare "/tickets" sends the browser to
 * app.vnsytek.com/tickets (404), and a signed-in user bounced between "/" and "/login" loops.
 */
class SubfolderDeploymentTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = 'https://app.vnsytek.com/rma';

    public function test_signed_in_users_opening_the_home_page_land_on_the_ticket_list_inside_the_sub_folder(): void
    {
        $this->actingAs(User::factory()->create());

        $this->visit('/')->assertRedirect(self::ROOT.'/tickets');
    }

    public function test_signed_in_users_opening_the_login_page_go_straight_to_the_ticket_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->visit('/login')->assertRedirect(self::ROOT.'/tickets');
    }

    public function test_guests_are_sent_to_the_login_page_inside_the_sub_folder(): void
    {
        $this->visit('/')->assertRedirect(self::ROOT.'/login');
        $this->visit('/tickets')->assertRedirect(self::ROOT.'/login');
    }

    /**
     * Send a request exactly as nginx hands it over: front controller at /rma/index.php,
     * URL kept as typed (the test client would strip the trailing slash of "/rma/").
     */
    private function visit(string $path): TestResponse
    {
        $request = Request::create(self::ROOT.$path, 'GET', server: [
            'SCRIPT_NAME' => '/rma/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);
        $response = TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle($request), $request);

        // A relative Location ("/tickets") would be resolved by the browser against the domain root.
        $this->assertStringStartsWith(self::ROOT.'/', (string) $response->headers->get('Location'), "Redirect from {$path} leaves the /rma folder.");

        return $response;
    }
}
