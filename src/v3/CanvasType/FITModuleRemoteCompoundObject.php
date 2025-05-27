<?php

namespace IiifPresentation\v3\CanvasType;

use IiifPresentation\v3\Controller\ItemController;
use Omeka\Api\Representation\MediaRepresentation;

class FITModuleRemoteCompoundObject implements CanvasTypeInterface
{
    public function getCanvas(MediaRepresentation $media, ItemController $controller): ?array
    {
        $canvases = [];
        $iiifEndpoint = $controller->settings()->get('fit_module_aws_iiif_endpoint');
        if ($iiifEndpoint) {
            $mediaId = $media->id();
            $url = $controller->url();
            foreach ($media->mediaData()['components'] as $index => $component) {
                $accessURL = $component['access'];
                if ($accessURL) {
                    $parsed_url = parse_url($accessURL);
                    $key = ltrim($parsed_url["path"], '/');
                    $extension = pathinfo($key, PATHINFO_EXTENSION);
                    if ($extension == 'tif') {
                        $canvases[] = [
                            'id' => $url->fromRoute('iiif-presentation-3/media/canvas', ['media-id' => $mediaId, 'index' => $index + 1], ['force_canonical' => true], true),
                            'type' => 'Canvas',
                            'label' => [
                                'none' => [
                                    $component['dcterms:title'],
                                ],
                            ],
                            'width' => $component['exif:width'],
                            'height' => $component['exif:height'],
                            'thumbnail' => [
                                [
                                    'id' => $component['thumbnail'],
                                    'type' => 'Image'
                                ]
                            ],
                            // 'metadata' => [
                            //     [
                            //         "label" => [
                            //             "none" => [
                            //                 "Title"
                            //             ]
                            //         ],
                            //         "value" => [
                            //             "none" => [
                            //                 $component['dcterms:title']
                            //             ]
                            //         ]
                            //     ]
                            // ],
                            'items' => [
                                [
                                    'id' => $url->fromRoute('iiif-presentation-3/media/annotation-page', ['media-id' => $mediaId, 'index' => $index + 1], ['force_canonical' => true], true),
                                    'type' => 'AnnotationPage',
                                    'items' => [
                                        [
                                            'id' => $url->fromRoute('iiif-presentation-3/media/annotation', ['media-id' => $mediaId, 'index' => $index + 1], ['force_canonical' => true], true),
                                            'type' => 'Annotation',
                                            'motivation' => 'painting',
                                            'body' => [
                                                'id' => $iiifEndpoint . str_replace("/", "%2F", substr($key, 0, -4)) . "/full/max/0/default.jpg",
                                                'type' => 'Image',
                                                "format" => "image/jpeg",
                                                'service' => [
                                                    'id' => $iiifEndpoint . str_replace("/", "%2F", substr($key, 0, -4)),
                                                    'type' => 'ImageService3',
                                                    'profile' => 'level2',
                                                ],
                                            ],
                                            'target' => $url->fromRoute('iiif-presentation-3/media/canvas', ['media-id' => $mediaId, 'index' => $index + 1], ['force_canonical' => true], true),
                                        ],
                                    ],
                                ],
                            ],
                        ];
                    }
                }
            }
        }
        return $canvases;
    }
}
