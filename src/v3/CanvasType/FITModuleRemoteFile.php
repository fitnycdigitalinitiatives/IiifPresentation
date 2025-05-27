<?php

namespace IiifPresentation\v3\CanvasType;

use IiifPresentation\v3\Controller\ItemController;
use Omeka\Api\Representation\MediaRepresentation;

class FITModuleRemoteFile implements CanvasTypeInterface
{
    public function getCanvas(MediaRepresentation $media, ItemController $controller): ?array
    {
        $mediaId = $media->id();
        $itemId = $media->item()->id();
        $accessURL = $media->mediaData()['access'];
        $mediaType = $media->mediaType();
        $iiifEndpoint = $controller->settings()->get('fit_module_aws_iiif_endpoint');
        if ((strpos($mediaType, 'image') === 0) && ($accessURL != '') && ($iiifEndpoint != '')) {
            $parsed_url = parse_url($accessURL);
            $key = ltrim($parsed_url["path"], '/');
            $extension = pathinfo($key, PATHINFO_EXTENSION);
            if ($extension == 'tif') {
                return [
                    'id' => $controller->url()->fromRoute('iiif-presentation-3/item/canvas', ['media-id' => $mediaId, 'item-id' => $itemId], ['force_canonical' => true], true),
                    'type' => 'Canvas',
                    'label' => [
                        'none' => [
                            $media->displayTitle(),
                        ],
                    ],
                    'width' => $media->value('exif:width')->asHtml(),
                    'height' => $media->value('exif:height')->asHtml(),
                    'thumbnail' => [
                        [
                            'id' => $media->mediaData()['thumbnail'],
                            'type' => 'Image'
                        ]
                    ],
                    'metadata' => $controller->iiifPresentation3()->getMetadata($media),
                    'items' => [
                        [
                            'id' => $controller->url()->fromRoute('iiif-presentation-3/item/annotation-page', ['media-id' => $mediaId, 'item-id' => $itemId], ['force_canonical' => true], true),
                            'type' => 'AnnotationPage',
                            'items' => [
                                [
                                    'id' => $controller->url()->fromRoute('iiif-presentation-3/item/annotation', ['media-id' => $mediaId, 'item-id' => $itemId], ['force_canonical' => true], true),
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
                                    'target' => $controller->url()->fromRoute('iiif-presentation-3/item/canvas', ['media-id' => $mediaId, 'item-id' => $itemId], ['force_canonical' => true], true),
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }
        return null;
    }
}
