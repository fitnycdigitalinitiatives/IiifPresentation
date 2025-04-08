<?php

namespace IiifPresentation\v2\CanvasType;

use IiifPresentation\v2\Controller\ItemController;
use Omeka\Api\Representation\MediaRepresentation;

class FITModuleRemoteFile implements CanvasTypeInterface
{
    public function getCanvas(MediaRepresentation $media, ItemController $controller): ?array
    {
        $accessURL = $media->mediaData()['access'];
        $mediaType = $media->mediaType();
        $iiifEndpoint = $controller->settings()->get('fit_module_aws_iiif_endpoint');
        if ((strpos($mediaType, 'image') === 0) && ($accessURL != '') && ($iiifEndpoint != '')) {
            $parsed_url = parse_url($accessURL);
            $key = ltrim($parsed_url["path"], '/');
            $extension = pathinfo($key, PATHINFO_EXTENSION);
            if ($extension == 'tif') {
                return [
                    '@id' => $controller->url()->fromRoute('iiif-presentation-2/item/canvas', ['media-id' => $media->id()], ['force_canonical' => true], true),
                    '@type' => 'sc:Canvas',
                    'label' => $media->displayTitle(),
                    'width' => $media->value('exif:width')->asHtml(),
                    'height' => $media->value('exif:height')->asHtml(),
                    'thumbnail' => [
                        '@id' => $media->mediaData()['thumbnail'],
                        '@type' => 'dctypes:Image',
                    ],
                    'metadata' => $controller->iiifPresentation2()->getMetadata($media),
                    'images' => [
                        [
                            '@type' => 'oa:Annotation',
                            'motivation' => 'sc:painting',
                            'resource' => [
                                '@id' => $media->originalUrl(),
                                '@type' => 'dctypes:Image',
                                'service' => [
                                    '@id' => $iiifEndpoint . str_replace("/", "%2F", substr($key, 0, -4)),
                                    '@context' => 'http://iiif.io/api/image/2/context.json',
                                    'profile' => 'http://iiif.io/api/image/2/level2.json',
                                ],
                            ],
                            'on' => $controller->url()->fromRoute('iiif-presentation-2/item/canvas', ['media-id' => $media->id()], ['force_canonical' => true], true),
                        ],
                    ],
                ];
            }
        }
        return null;
    }
}
