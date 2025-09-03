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

use core_ai\aiactions\base;
use core_ai\provider;
use GuzzleHttp\Psr7\Response;
use local_aixtension\aiactions\convert_text_to_speech;

/**
 * Test OpenAI processor methods for text-to-speech actions.
 *
 * @package   aiprovider_openai_extension
 * @copyright   2025 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \aiprovider_openai_extension\process_convert_text_to_speech
 */
final class process_convert_text_to_speech_test extends \advanced_testcase {
    /** @var string Raw audio bytes for a successful response. */
    protected string $responsebodymp3;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        // Load a response body from a file.
        $this->responsebodymp3 = file_get_contents(
            self::get_fixture_path('aiprovider_openai_extension', 'hello_world.mp3')
        );
        $this->create_provider();
        $this->create_action();
    }

    /**
     * Create the provider object.
     */
    private function create_provider(): void {
        $this->provider = new \aiprovider_openai_extension\provider();
    }

    /**
     * Create the action object.
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $this->action =  new convert_text_to_speech(
            contextid: 1,
            userid: $userid,
            texttoread: 'This is a sample text to read',
            voice: 'alloy',
            format: 'mp3',
        );
    }

    /**
     * Test create_request_object
     */
    public function test_create_request_object(): void {
        $processor = new process_convert_text_to_speech($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $requestdata = (object) json_decode($request->getBody()->getContents());

        $this->assertEquals('This is a sample text to read', $requestdata->input);
        $this->assertEquals('alloy', $requestdata->voice);
        $this->assertEquals('mp3', $requestdata->format);
    }

    /**
     * Test the API error response handler method.
     */
    public function test_handle_api_error(): void {
        $responses = [
            500 => new Response(500, ['Content-Type' => 'application/json']),
            503 => new Response(503, ['Content-Type' => 'application/json']),
            401 => new Response(401, ['Content-Type' => 'application/json'],
                '{"error": {"message": "Invalid Authentication"}}'),
            404 => new Response(404, ['Content-Type' => 'application/json'],
                '{"error": {"message": "You must be a member of an organization to use the API"}}'),
            429 => new Response(429, ['Content-Type' => 'application/json'],
                '{"error": {"message": "Rate limit reached for requests"}}'),
        ];

        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_error');

        foreach ($responses as $status => $response) {
            $result = $method->invoke($processor, $response);
            $this->assertEquals($status, $result['errorcode']);
            if ($status == 500) {
                $this->assertEquals('Internal Server Error', $result['errormessage']);
            } else if ($status == 503) {
                $this->assertEquals('Service Unavailable', $result['errormessage']);
            } else {
                $this->assertStringContainsString($response->getBody()->getContents(), $result['errormessage']);
            }
        }
    }

    /**
     * Test the API success response handler method with raw audio.
     * @covers ::handle_api_success
     */
    public function test_handle_api_success(): void {
        $this->resetAfterTest();
        $response = new Response(
            200,
            ['Content-Type' => 'audio/mpeg'],
            $this->responsebodymp3
        );

        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('audio/mpeg', $result['mimetype']);
        $this->assertStringContainsString('openai-tts', $result['filename']);
        $this->assertEquals(strlen($this->responsebodymp3), $result['filesize']);
    }

    /**
     * Test query_ai_api for a successful call (returns audio stream).
     * @covers ::query_ai_api
     */
    public function test_query_ai_api_success(): void {
        $this->resetAfterTest();
        ['mock' => $mock] = $this->get_mocked_http_client();

        // OpenAI returns the audio bytes directly for TTS.
        $mock->append(new Response(
            200,
            ['Content-Type' => 'audio/mpeg'],
            $this->responsebodymp3
        ));

        $this->setAdminUser();

        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor);

        $this->assertTrue($result['success']);
        $this->assertEquals('audio/mpeg', $result['mimetype']);
        $this->assertGreaterThan(0, $result['filesize']);
    }

    /**
     * Test prepare_response success.
     * @covers ::prepare_response
     */
    public function test_prepare_response_success(): void {
        $this->resetAfterTest();
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $fs = get_file_storage();
        $file = $fs->create_file_from_string(
            (object)[
                'contextid' => 1,
                'component' => 'aiprovider_openai_extension',
                'filearea'  => 'generatedaudio',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => 'speech.mp3',
            ],
            'test.mp3',
        );
        $response = [
            'success' => true,
            'mimetype' => 'audio/mp3',
            'filename' => 'speech.mp3',
            'filesize' => 1234,
            'fileid' => $file->get_id(), // Assuming your processor sets this.
        ];
        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('convert_text_to_speech', $result->get_actionname());
        $responsedata = $result->get_response_data();
        $this->assertEquals('audio/mp3', $responsedata['mimetype']);
        $this->assertEquals('speech.mp3', $responsedata['filename']);
    }

    /**
     * Test prepare_response error.
     * @covers ::prepare_response
     */
    public function test_prepare_response_error(): void {
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => false,
            'errorcode' => 500,
            'errormessage' => 'Internal server error.',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('convert_text_to_speech', $result->get_actionname());
        $this->assertEquals(500, $result->get_errorcode());
        $this->assertEquals('Internal server error.', $result->get_errormessage());
    }

    /**
     * Test storing the audio bytes as a moodle file.
     * @covers ::store_audio_file
     */
    public function test_store_audio_file(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'store_audio_file');

        $contextid = 1;
        $content = $this->responsebodymp3;
        $filename = 'speech.mp3';
        $mimetype = 'audio/mp3';

        $filenobj = $method->invoke($processor, $contextid, $content, $filename, $mimetype);

        $this->assertEquals('speech.mp3', $filenobj->get_filename());
        $this->assertEquals('audio/mp3', $filenobj->get_mimetype());
        $this->assertGreaterThan(0, $filenobj->get_filesize());
    }

    /**
     * Test process(): full happy path.
     * @covers ::process
     */
    public function test_process(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();

        // TTS returns audio bytes.
        $mock->append(new Response(
            200,
            ['Content-Type' => 'audio/mpeg'],
            $this->responsebodymp3
        ));

        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('convert_text_to_speech', $result->get_actionname());
        $this->assertEquals('audio/mp3', $result->get_response_data()['mimetype']);
        $this->assertStringEndsWith('.mp3', $result->get_response_data()['filename']);
        $this->assertGreaterThan(0, $result->get_response_data()['filesize']);
        // If you return a stored file id/url in response_data, you can assert those too.
    }

    /**
     * Test process() with error.
     * @covers ::process
     */
    public function test_process_error(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();

        // Error from OpenAI.
        $mock->append(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['message' => 'Invalid Authentication']]),
        ));

        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('convert_text_to_speech', $result->get_actionname());
        $this->assertEquals(401, $result->get_errorcode());
        $this->assertEquals('Invalid Authentication', $result->get_errormessage());
    }

    /**
     * Test process() with user rate limiter.
     * @covers ::process
     */
    public function test_process_with_user_rate_limiter(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $clock = $this->mock_clock_with_frozen();

        // Enable user-level rate limiting for THIS plugin.
        set_config('enableuserratelimit', 1, 'aiprovider_openai_extension');
        set_config('userratelimit', 1, 'aiprovider_openai_extension');

        ['mock' => $mock] = $this->get_mocked_http_client();

        // Case 1: Below limit.
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Exceeded for same user in same window.
        $clock->bump(HOURSECS - 10);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('User rate limit exceeded', $result->get_errormessage());

        // Case 3: Different user is not blocked by user-level limiter.
        $this->setUser($user2);
        $this->create_provider();
        $this->create_action($user2->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 4: Window passes; limiter resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }

    /**
     * Test process() with global rate limiter.
     * @covers ::process
     */
    public function test_process_with_global_rate_limiter(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $clock = $this->mock_clock_with_frozen();

        // Enable global rate limiting for THIS plugin.
        set_config('enableglobalratelimit', 1, 'aiprovider_openai_extension');
        set_config('globalratelimit', 1, 'aiprovider_openai_extension');

        ['mock' => $mock] = $this->get_mocked_http_client();

        // Case 1: Below global limit.
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Global limit reached.
        $clock->bump(HOURSECS - 10);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('Global rate limit exceeded', $result->get_errormessage());

        // Case 3: Different user also blocked while within window.
        $this->setUser($user2);
        $this->create_provider();
        $this->create_action($user2->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());

        // Case 4: Window passes; global limiter resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(new Response(200, ['Content-Type' => 'audio/mpeg'], $this->responsebodymp3));
        $processor = new process_convert_text_to_speech($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }
}
