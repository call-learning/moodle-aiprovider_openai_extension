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
use stored_file;

/**
 * Test OpenAI processor methods for speech to text actions.
 *
 * @package   aiprovider_openai_extension
 * @copyright   2025 Laurent David <laurent@call-learning.fr>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_openai_extension\process_convert_speech_to_text
 */
final class process_convert_speech_to_text_test extends \advanced_testcase {
    /** @var stored_file $audiomp3 . */
    protected stored_file $audiomp3;

    /** @var provider The provider that will process the action. */
    protected provider $provider;

    /** @var base The action to process. */
    protected base $action;

    /**
     * Set up the test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $fs = get_file_storage();
        $this->audiomp3 = $fs->create_file_from_pathname(
            [
                'contextid' => 1,
                'component' => 'aiprovider_openai_extension',
                'filearea' => 'testfiles',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'hello_world.mp3',
            ],
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
     *
     * @param int $userid The user id to use in the action.
     */
    private function create_action(int $userid = 1): void {
        $this->action = new \local_aixtension\aiactions\convert_speech_to_text(
            contextid: 1,
            userid: $userid,
            audiofile: $this->audiomp3,
        );
    }

    /**
     * Test create_request_object
     */
    public function test_create_request_object(): void {
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);

        // We're working with a private method here, so we need to use reflection.
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $request = $method->invoke($processor, 1);

        $this->assertStringContainsString('transcriptions', (string) $request->getUri());
        // Decode multipart request.
        $body = (string) $request->getBody();
        // Extract boundary.
        $bodyparts = explode('--', $body);
        $fields = [];
        foreach ($bodyparts as $part) {
            if (empty(trim($part))) {
                continue;
            }
            $content = explode("\r\n", $part, 4);
            if (count($content) < 4) {
                continue;
            }
            $matches = [];
            $field = preg_match('/name="([^"]+)"/', $content[1], $matches) ? $matches[1] : null;
            if (!$field) {
                continue;
            }
            $fields[$field] = trim($content[3] ?? '');
        }
        $this->assertEquals('whisper-1', $fields['model']);
        $this->assertEquals('verbose_json', $fields['response_format']);
        $this->assertEquals('["word","segment"]', $fields['timestamps_granularities']);
        $this->assertStringContainsString('Content-Type: audio/mpeg', $fields['file']);
    }

    /**
     * Test the API error response handler method.
     */
    public function test_handle_api_error(): void {
        $responses = [
            500 => new Response(500, ['Content-Type' => 'application/json']),
            503 => new Response(503, ['Content-Type' => 'application/json']),
            401 => new Response(
                401,
                ['Content-Type' => 'application/json'],
                '{"error": {"message": "Invalid Authentication"}}'
            ),
            404 => new Response(
                404,
                ['Content-Type' => 'application/json'],
                '{"error": {"message": "You must be a member of an organization to use the API"}}'
            ),
            429 => new Response(
                429,
                ['Content-Type' => 'application/json'],
                '{"error": {"message": "Rate limit reached for requests"}}'
            ),
        ];

        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
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
     * @var string SAMPLE answer
     *
     * Check with curl:
     *  curl https://api.openai.com/v1/audio/transcriptions
     *     -H "Authorization: Bearer $OPENAI_API_KEY"
     *     -H "Content-Type: multipart/form-data"
     *     -F file="@<SITEHOMEFOLDER>/local/aixtension/tests/fixtures/hello_world.mp3"
     *     -F model="whisper-1"
     *     -F "timestamp_granularities[]=segment"
     *     -F "timestamp_granularities[]=word"
     *     -F "response_format=verbose_json" | jq
     **/
    public const SAMPLE_ANSWER = [
        "task" => "transcribe",
        "language" => "english",
        "duration" => 1.0199999809265137,
        "text" => "Hello world.",
        "segments" => [
            [
                "id" => 0,
                "seek" => 0,
                "start" => 0,
                "end" => 0.6800000071525574,
                "text" => " Hello world.",
                "tokens" => [
                    50364,
                    2425,
                    1002,
                    13,
                    50400,
                ],
                "temperature" => 0,
                "avg_logprob" => -0.8113626837730408,
                "compression_ratio" => 0.6000000238418579,
                "no_speech_prob" => 0.06314413994550705,
            ],
        ],
        "words" => [
            [
                "word" => "Hello",
                "start" => 0,
                "end" => 0.36000001430511475,
            ],
            [
                "word" => "world",
                "start" => 0.36000001430511475,
                "end" => 0.6800000071525574,
            ],
        ],
        "usage" => [
            "type" => "duration",
            "seconds" => 2,
        ],
    ];

    /**
     * Test the API success response handler method.
     * @covers ::handle_api_success
     */
    public function test_handle_api_success(): void {
        $this->resetAfterTest();
        $response = new Response(
            200,
            ['Content-Type' => 'json/application'],
            json_encode(self::SAMPLE_ANSWER)
        );

        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'handle_api_success');

        $result = $method->invoke($processor, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('english', $result['detectedlanguage']);
        $this->assertEquals('Hello world.', $result['text']);
        $this->assertCount(2, $result['stats']);
        $this->assertEquals(0.0, $result['stats'][0]['start']);
        $this->assertEquals(0.36000001430511475, $result['stats'][0]['end']);
        $this->assertEquals('Hello', $result['stats'][0]['text']);
        $this->assertEquals(0.36000001430511475, $result['stats'][1]['start']);
        $this->assertEquals(0.6800000071525574, $result['stats'][1]['end']);
        $this->assertEquals('world', $result['stats'][1]['text']);

        // Check confidence calculation.
        $this->assertEquals(19, $result['stats'][0]['confidence']); // From logprob -0.8113626837730408.
        $this->assertEquals(19, $result['stats'][1]['confidence']); // From logprob -0.8113626837730408.
    }

    /**
     * Test query_ai_api for a successful call (returns audio stream).
     * @covers ::query_ai_api
     */
    public function test_query_ai_api_success(): void {
        $this->resetAfterTest();
        ['mock' => $mock] = $this->get_mocked_http_client();

        // OpenAI returns the audio bytes directly for STT.
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );

        $this->setAdminUser();

        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'query_ai_api');
        $result = $method->invoke($processor);

        $this->assertTrue($result['success']);
        $this->assertEquals('english', $result['detectedlanguage']);
        $this->assertEquals('Hello world.', $result['text']);
    }

    /**
     * Test prepare_response success.
     * @covers ::prepare_response
     */
    public function test_prepare_response_success(): void {
        $this->resetAfterTest();
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => true,
            'detectedlanguage' => 'english',
            'text' => 'Hello world.',
            'stats' =>
                [
                    [
                        'text' => 'Hello',
                        'start' => 0.0,
                        'end' => 0.36000001430511475,
                        'confidence' => 19,
                    ],
                    [
                        'text' => 'world',
                        'start' => 0.36000001430511475,
                        'end' => 0.6800000071525574,
                        'confidence' => 19,
                    ],
                ],
        ];
        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('convert_speech_to_text', $result->get_actionname());
        $responsedata = $result->get_response_data();
        $this->assertEquals('english', $responsedata['detectedlanguage']);
        $this->assertEquals('Hello world.', $responsedata['text']);
    }

    /**
     * Test prepare_response error.
     * @covers ::prepare_response
     */
    public function test_prepare_response_error(): void {
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $method = new \ReflectionMethod($processor, 'prepare_response');

        $response = [
            'success' => false,
            'errorcode' => 500,
            'errormessage' => 'Internal server error.',
        ];

        $result = $method->invoke($processor, $response);

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('convert_speech_to_text', $result->get_actionname());
        $this->assertEquals(500, $result->get_errorcode());
        $this->assertEquals('Internal server error.', $result->get_errormessage());
    }

    /**
     * Test process(): full happy path.
     * @covers ::process
     */
    public function test_process(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        ['mock' => $mock] = $this->get_mocked_http_client();

        // OpenAI returns the audio bytes directly for STT.
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertTrue($result->get_success());
        $this->assertEquals('convert_speech_to_text', $result->get_actionname());
        $responsedata = $result->get_response_data();
        $this->assertEquals('english', $responsedata['detectedlanguage']);
        $this->assertEquals('Hello world.', $responsedata['text']);
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

        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();

        $this->assertInstanceOf(\core_ai\aiactions\responses\response_base::class, $result);
        $this->assertFalse($result->get_success());
        $this->assertEquals('convert_speech_to_text', $result->get_actionname());
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
        set_config('enableuserratelimit', 1, 'aiprovider_openai');
        set_config('userratelimit', 1, 'aiprovider_openai');

        ['mock' => $mock] = $this->get_mocked_http_client();

        // Case 1: Below limit.
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Exceeded for same user in same window.
        $clock->bump(HOURSECS - 10);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('User rate limit exceeded', $result->get_errormessage());

        // Case 3: Different user is not blocked by user-level limiter.
        $this->setUser($user2);
        $this->create_provider();
        $this->create_action($user2->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 4: Window passes; limiter resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
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
        set_config('enableglobalratelimit', 1, 'aiprovider_openai');
        set_config('globalratelimit', 1, 'aiprovider_openai');

        ['mock' => $mock] = $this->get_mocked_http_client();

        // Case 1: Below global limit.
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());

        // Case 2: Global limit reached.
        $clock->bump(HOURSECS - 10);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());
        $this->assertEquals('Global rate limit exceeded', $result->get_errormessage());

        // Case 3: Different user also blocked while within window.
        $this->setUser($user2);
        $this->create_provider();
        $this->create_action($user2->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertFalse($result->get_success());
        $this->assertEquals(429, $result->get_errorcode());

        // Case 4: Window passes; global limiter resets.
        $clock->bump(11);
        $this->setUser($user1);
        $this->create_provider();
        $this->create_action($user1->id);
        $mock->append(
            new Response(
                200,
                ['Content-Type' => 'json/application'],
                json_encode(self::SAMPLE_ANSWER)
            )
        );
        $processor = new \aiprovider_openai_extension\process_convert_speech_to_text($this->provider, $this->action);
        $result = $processor->process();
        $this->assertTrue($result->get_success());
    }
}
