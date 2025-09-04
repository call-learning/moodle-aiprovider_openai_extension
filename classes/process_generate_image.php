<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
namespace aiprovider_openai_extension;

use aiprovider_openai\abstract_processor;
use core\http_client;
use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class to process using OpenAI's API with b64_json. Here we try to have a standard way of dealing with images
 * so we kind of override completely the usual/core image provider and use the new model (gpt-image-1).
 * It requires some different handling as the response is base64 encoded image data rather than a URL and also converts
 * the aspect ratio to a size parameter while transforming the quality and style parameter to the right one.
 * This class extends the existing image generation process to handle base64 encoded images
 * specifically for gpt-image-1.
 * This is a bit of a tweak to the existing openAI image generation process so we can use the new model.
 */
class process_generate_image extends \aiprovider_openai\process_generate_image {
    /** @var int The number of images to generate dall-e-3 only supports 1. */
    private int $numberimages = 1;

    #[\Override]
    protected function get_model(): string {
        return get_config('aiprovider_openai_extension', 'action_generate_image_model');
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        // Here the response format is always b64_json. Few parameters are not present for the new models like
        // style, response_format, quality.
        $quality = $this->action->get_configuration('quality');
        switch ($quality) {
            case 'standard':
                $quality = 'low';
                break;
            case 'hd':
                $quality = 'high';
                break;
            case 'auto':
            default:
                $quality = 'medium';
                break;
        }
        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode((object) [
                'prompt' => $this->action->get_configuration('prompttext'),
                'model' => $this->get_model(),
                'n' => $this->numberimages,
                'quality' => $quality,
                'size' => $this->calculate_size($this->action->get_configuration('aspectratio')),
                'user' => $userid,
            ]),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    /**
     * Convert the given aspect ratio to an image size
     * that is compatible with the OpenAI API.
     *
     * @param string $ratio The aspect ratio of the image.
     * @return string The size of the image.
     */
    private function calculate_size(string $ratio): string {
        if ($ratio === 'square') {
            $size = '1024x1024';
        } else if ($ratio === 'landscape') {
            $size = '1536x1024';
        } else if ($ratio === 'portrait') {
            $size = '1024x1536';
        } else {
            throw new \coding_exception('Invalid aspect ratio: ' . $ratio);
        }
        return $size;
    }

    #[\Override]
    protected function query_ai_api(): array {
        $response = abstract_processor::query_ai_api();

        // If the request was successful, save the URL to a file.
        if ($response['success']) {
            $fileobj = $this->base_64_to_file(
                $this->action->get_configuration('userid'),
                $response['base64data'],
                $response['outputformat']
            );
            // Add the file to the response, so the calling placement can do whatever they want with it.
            $response['draftfile'] = $fileobj;
        }

        return $response;
    }


    /**
     * Convert the base64 image data to a stored file.
     *
     * Placements can't interact with the provider AI directly,
     * therefore we need to provide the image file in a format that can
     * be used by placements. So we use the file API.
     *
     * @param int $userid The user id.
     * @param string $base64data The base64 encoded image data.
     * @return \stored_file The file object.
     */
    private function base_64_to_file(int $userid, string $base64data, string $fileextension): \stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        $client = \core\di::get(http_client::class);
        $filename = 'openai_extension' . time() . ".$fileextension";
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempdst, base64_decode($base64data));
        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        // We put the file in the user draft area initially.
        // Placements (on behalf of the user) can then move it to the correct location.
        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        return $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));
    }


    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $responsebody = $response->getBody();
        $bodyobj = json_decode($responsebody->getContents());

        return [
            'success' => true,
            'sourceurl' => null, // No URL is provided in b64_json response.
            'revisedprompt' => null, // No revised prompt is provided.
            'base64data' => $bodyobj->data[0]->b64_json,
            'outputformat' => $bodyobj->output_format,
        ];
    }
}