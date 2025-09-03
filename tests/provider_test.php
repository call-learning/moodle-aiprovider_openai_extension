<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
namespace aiprovider_openai_extension;


use local_aixtension\aiactions\convert_text_to_speech;

/**
 * Test OpenAI provider methods.
 *
 * @package   aiprovider_openai_extension
 * @copyright   2025 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \aiprovider_openai_extension\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Test get_action_list
     * @covers ::get_action_list
     */
    public function test_get_action_list(): void {
        $provider = new provider();
        $actionlist = $provider->get_action_list();
        $this->assertIsArray($actionlist);
        $this->assertCount(1, $actionlist);
        $this->assertContains(convert_text_to_speech::class, $actionlist);
    }

    /**
     * Test generate_userid.
     * @covers ::generate_userid
     */
    public function test_generate_userid(): void {
        $provider = new provider();
        $userid = $provider->generate_userid(1);

        // Assert that the generated userid is a string of proper length.
        $this->assertIsString($userid);
        $this->assertEquals(64, strlen($userid));
    }

    /**
     * Test is_request_allowed.
     * @covers ::is_request_allowed
     */
    public function test_is_request_allowed(): void {
        $this->resetAfterTest();

        // Set plugin config rate limiter settings.
        set_config('enableglobalratelimit', 1, 'aiprovider_openai');
        set_config('globalratelimit', 5, 'aiprovider_openai');
        set_config('enableuserratelimit', 1, 'aiprovider_openai');
        set_config('userratelimit', 3, 'aiprovider_openai');

        $contextid = 1;
        $prompttext = 'Generate an audio file from this text.';
        $userid = 1;
        $action = new convert_text_to_speech(
            contextid: $contextid,
            userid: $userid,
            texttoread: $prompttext,
            voice: 'alloy', // Example voice.
            format: 'mp3', // Example format.
        );
        $provider = new provider();

        // Make 3 requests, all should be allowed.
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($provider->is_request_allowed($action));
        }

        // The 4th request for the same user should be denied.
        $result = $provider->is_request_allowed($action);
        $this->assertFalse($result['success']);
        $this->assertEquals('User rate limit exceeded', $result['errormessage']);

        // Change user id to make a request for a different user, should pass (4 requests for global rate).
        $action = new convert_text_to_speech(
            contextid: $contextid,
            userid: 2,
            texttoread: $prompttext,
            voice: 'alloy', // Example voice.
            format: 'mp3', // Example format.
        );
        $this->assertTrue($provider->is_request_allowed($action));

        // Make a 5th request for the global rate limit, it should be allowed.
        $this->assertTrue($provider->is_request_allowed($action));

        // The 6th request should be denied.
        $result = $provider->is_request_allowed($action);
        $this->assertFalse($result['success']);
        $this->assertEquals('Global rate limit exceeded', $result['errormessage']);
    }

    /**
     * Test is_provider_configured.
     * @covers ::is_provider_configured
     */
    public function test_is_provider_configured(): void {
        $this->resetAfterTest();

        // No configured values.
        $provider = new \aiprovider_openai_extension\provider();
        $this->assertFalse($provider->is_provider_configured());

        // Properly configured values.
        set_config('apikey', '123', 'aiprovider_openai');
        $provider = new \aiprovider_openai_extension\provider();
        $this->assertTrue($provider->is_provider_configured());
    }
}
