<?php

namespace IiifPresentation\v3\ControllerPlugin;

use IiifPresentation\ControllerPlugin\AbstractIiifPresentation;
use IiifPresentation\v3\CanvasType\Manager as CanvasTypeManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;

class IiifPresentation extends AbstractIiifPresentation
{
    protected $canvasTypeManager;

    public function __construct(CanvasTypeManager $canvasTypeManager)
    {
        $this->canvasTypeManager = $canvasTypeManager;
    }

    /**
     * Get a IIIF Presentation collection of Omeka items.
     *
     * @see https://iiif.io/api/presentation/3.0/#51-collection
     */
    public function getItemsCollection(array $itemIds, string $label)
    {
        $controller = $this->getController();
        $collection = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $controller->url()->fromRoute(null, [], ['force_canonical' => true], true),
            'type' => 'Collection',
            'label' => [
                'none' => [$label],
            ],
        ];
        foreach ($itemIds as $itemId) {
            $item = $controller->api()->read('items', $itemId)->getContent();
            $collection['items'][] = [
                'id' => $controller->url()->fromRoute('iiif-presentation-3/item/manifest', ['item-id' => $item->id()], ['force_canonical' => true], true),
                'type' => 'Manifest',
                'label' => [
                    'none' => [$item->displayTitle()],
                ],
            ];
        }
        // Allow modules to modify the collection.
        $args = $this->triggerEvent(
            'iiif_presentation.3.item.collection',
            [
                'collection' => $collection,
                'item_ids' => $itemIds,
            ]
        );
        return $args['collection'];
    }

    /**
     * Get a IIIF Presentation collection of Omeka item sets.
     *
     * @see https://iiif.io/api/presentation/3.0/#51-collection
     */
    public function getItemSetsCollection(array $itemSetIds)
    {
        $controller = $this->getController();
        $collection = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $controller->url()->fromRoute(null, [], ['force_canonical' => true], true),
            'type' => 'Collection',
            'label' => [
                'none' => [$controller->translate('Item Sets Collection')],
            ],
        ];
        foreach ($itemSetIds as $itemSetId) {
            $itemSet = $controller->api()->read('item_sets', $itemSetId)->getContent();
            $collection['items'][] = [
                'id' => $controller->url()->fromRoute('iiif-presentation-3/item-set/collection', ['item-set-id' => $itemSet->id()], ['force_canonical' => true], true),
                'type' => 'Collection',
                'label' => [
                    'none' => [$itemSet->displayTitle()],
                ],
            ];
        }
        // Allow modules to modify the collection.
        $args = $this->triggerEvent(
            'iiif_presentation.3.item_set.collections',
            [
                'collection' => $collection,
                'item_set_ids' => $itemSetIds,
            ]
        );
        return $args['collection'];
    }

    /**
     * Get a IIIF Presentation collection for an Omeka item set.
     *
     * @see https://iiif.io/api/presentation/3.0/#51-collection
     */
    public function getItemSetCollection(int $itemSetId)
    {
        $controller = $this->getController();
        $itemSet = $controller->api()->read('item_sets', $itemSetId)->getContent();
        $itemIds = $controller->api()->search('items', ['item_set_id' => $itemSetId], ['returnScalar' => 'id'])->getContent();
        $collection = $this->getItemsCollection($itemIds, $itemSet->displayTitle());
        // Allow modules to modify the collection.
        $args = $this->triggerEvent(
            'iiif_presentation.3.item_set.collection',
            [
                'collection' => $collection,
                'item_set' => $itemSet,
            ]
        );
        return $args['collection'];
    }

    /**
     * Get a IIIF Presentation manifest for an Omeka item.
     *
     * @see https://iiif.io/api/presentation/3.0/#52-manifest
     */
    public function getItemManifest(int $itemId)
    {
        $controller = $this->getController();
        $item = $controller->api()->read('items', $itemId)->getContent();
        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $controller->url()->fromRoute(null, [], ['force_canonical' => true], true),
            'type' => 'Manifest',
            'behavior' => ['individuals', 'no-auto-advance'], // Default behaviors
            'viewingDirection' => 'left-to-right', // Default viewing direction
            'label' => [
                'none' => [$item->displayTitle()],
            ],
            'summary' => [
                'none' => [$item->displayDescription()],
            ],
            'provider' => [
                [
                    'id' => $controller->url()->fromRoute('top', [], ['force_canonical' => true]),
                    'type' => 'Agent',
                    'label' => ['none' => [$controller->settings()->get('installation_title')]],
                ],
            ],
            'seeAlso' => [
                [
                    'id' => $controller->url()->fromRoute('api/default', ['resource' => 'items', 'id' => $item->id()], ['force_canonical' => true, 'query' => ['pretty_print' => true]]),
                    'type' => 'Dataset',
                    'label' => ['none' => [$controller->translate('Item metadata')]],
                    'format' => 'application/ld+json',
                    'profile' => 'https://www.w3.org/TR/json-ld/',
                ],
            ],
            'metadata' => $this->getMetadata($item),
        ];
        // Manifest thumbnail.
        $primaryMedia = $item->primaryMedia();
        if ($primaryMedia) {
            if ($primaryMedia->ingester() == 'remoteFile') {
                $thumbnailURL = $primaryMedia->mediaData()['thumbnail'];
                if ($thumbnailURL) {
                    $manifest['thumbnail'] = [
                        [
                            'id' => $thumbnailURL,
                            'type' => 'Image'
                        ]
                    ];
                }
            } else {
                $manifest['thumbnail'] = [
                    [
                        'id' => $primaryMedia->thumbnailUrl('medium'),
                        'type' => 'Image',
                    ],
                ];
            }
        }
        // Default required statement if no rights exist
        $manifest['requiredStatement'] = ["label" => ["en" => ["Attribution"]], "value" => ["en" => ["This resource has been made available online by the Fashion Institute of Technology Gladys Marcus Library"]]];
        $literalRights = $item->value('dcterms:rights', ['all' => true, 'type' => 'literal']);
        $requiredStatement = [];
        foreach ($literalRights as $literalRight) {
            $requiredStatement[] =  $literalRight;
        }
        if ($requiredStatement) {
            $manifest['requiredStatement'] = ["label" => ["en" => ["Rights"]], "value" => ["en" => [implode(". ", $requiredStatement)]]];
        }
        $rights = $item->value('dcterms:rights', ['all' => true, 'type' => 'uri']);
        $hasrights = false;
        // Get uri of rights statement, let creative commons take precedence
        foreach ($rights as $rightsstatement) {
            if (str_contains($rightsstatement->uri(), "creativecommons.org")) {
                $manifest['rights'] = $rightsstatement->uri();
                $hasrights = true;
                break;
            }
        }
        if (!$hasrights) {
            foreach ($rights as $rightsstatement) {
                if (str_contains($rightsstatement->uri(), "rightsstatements.org")) {
                    $manifest['rights'] = $rightsstatement->uri();
                    break;
                }
            }
        }
        // Manifest homepages (this item is assigned to these sites).
        foreach ($item->sites() as $site) {
            $manifest['homepage'][] = [
                'id' => $controller->url()->fromRoute('site/resource-id', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'show', 'id' => $item->id()], ['force_canonical' => true]),
                'type' => 'Text',
                'label' => [
                    'none' => [sprintf('Item in site: %s', $site->title())],
                ],
                'format' => 'text/html',
            ];
        }

        foreach ($item->media() as $media) {
            $renderer = $media->renderer();
            if (!$this->canvasTypeManager->has($renderer)) {
                // There is no canvas type for this renderer.
                continue;
            }
            $canvasType = $this->canvasTypeManager->get($renderer);
            // Compound objects return an array of canvases whereas other return just a single
            if ($renderer == 'remoteCompoundObject') {
                $canvases = $canvasType->getCanvas($media, $controller);
                if (!$canvases) {
                    // A canvas could not be created.
                    continue;
                }
                foreach ($canvases as $canvas) {
                    // Allow modules to modify the canvas.
                    $args = $this->triggerEvent(
                        'iiif_presentation.3.media.canvas',
                        [
                            'canvas' => $canvas,
                            'canvas_type' => $canvasType,
                            'media' => $media,
                        ]
                    );
                    // Set the canvas to the manifest.
                    $manifest['items'][] = $args['canvas'];
                }
            } else {
                $canvas = $canvasType->getCanvas($media, $controller);
                if (!$canvas) {
                    // A canvas could not be created.
                    continue;
                }
                // Allow modules to modify the canvas.
                $args = $this->triggerEvent(
                    'iiif_presentation.3.media.canvas',
                    [
                        'canvas' => $canvas,
                        'canvas_type' => $canvasType,
                        'media' => $media,
                    ]
                );
                // Set the canvas to the manifest.
                $manifest['items'][] = $args['canvas'];
            }
        }
        // Allow modules to modify the manifest.
        $args = $this->triggerEvent(
            'iiif_presentation.3.item.manifest',
            [
                'manifest' => $manifest,
                'item' => $item,
            ]
        );
        return $args['manifest'];
    }

    /**
     * Get a IIIF Presentation manifest for an Omeka media.
     *
     * @see https://iiif.io/api/presentation/3.0/#52-manifest
     */
    public function getMediaManifest(int $itemId, int $mediaId)
    {
        $controller = $this->getController();
        $url = $controller->url();
        $item = $controller->api()->read('items', $itemId)->getContent();
        $media = $controller->api()->read('media', $mediaId)->getContent();
        $renderer = $media->renderer();
        $mediaData = $media->mediaData();
        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $controller->url()->fromRoute(null, [], ['force_canonical' => true], true),
            'type' => 'Manifest',
            'behavior' => ['individuals', 'no-auto-advance'], // Default behaviors
            'viewingDirection' => 'left-to-right', // Default viewing direction
            'label' => [
                'none' => [$item->displayTitle()],
            ],
            'summary' => [
                'none' => [$item->displayDescription()],
            ],
            'provider' => [
                [
                    'id' => $controller->url()->fromRoute('top', [], ['force_canonical' => true]),
                    'type' => 'Agent',
                    'label' => ['none' => [$controller->settings()->get('installation_title')]],
                ],
            ],
            'seeAlso' => [
                [
                    'id' => $controller->url()->fromRoute('api/default', ['resource' => 'items', 'id' => $item->id()], ['force_canonical' => true, 'query' => ['pretty_print' => true]]),
                    'type' => 'Dataset',
                    'label' => ['none' => [$controller->translate('Item metadata')]],
                    'format' => 'application/ld+json',
                    'profile' => 'https://www.w3.org/TR/json-ld/',
                ],
            ],
            'metadata' => $this->getMetadata($item),
        ];
        // Manifest thumbnail.

        if ($renderer == 'remoteFile') {
            $thumbnailURL = $mediaData['thumbnail'];
            if ($thumbnailURL) {
                $manifest['thumbnail'] = [
                    [
                        'id' => $thumbnailURL,
                        'type' => 'Image'
                    ]
                ];
            }
        } elseif ($renderer == 'remoteCompoundObject') {
            foreach ($mediaData['components'] as $component) {
                if ($component['thumbnail']) {
                    $manifest['thumbnail'] = [
                        [
                            'id' => $component['thumbnail'],
                            'type' => 'Image'
                        ]
                    ];
                    break;
                }
            }
        } else {
            $manifest['thumbnail'] = [
                [
                    'id' => $media->thumbnailUrl('medium'),
                    'type' => 'Image',
                ],
            ];
        }
        // Default required statement if no rights exist
        $manifest['requiredStatement'] = ["label" => ["en" => ["Attribution"]], "value" => ["en" => ["This resource has been made available online by the Fashion Institute of Technology Gladys Marcus Library"]]];
        $literalRights = $item->value('dcterms:rights', ['all' => true, 'type' => 'literal']);
        $requiredStatement = [];
        foreach ($literalRights as $literalRight) {
            $requiredStatement[] =  $literalRight;
        }
        if ($requiredStatement) {
            $manifest['requiredStatement'] = ["label" => ["en" => ["Rights"]], "value" => ["en" => [implode(". ", $requiredStatement)]]];
        }
        $rights = $item->value('dcterms:rights', ['all' => true, 'type' => 'uri']);
        $hasrights = false;
        // Get uri of rights statement, let creative commons take precedence
        foreach ($rights as $rightsstatement) {
            if (str_contains($rightsstatement->uri(), "creativecommons.org")) {
                $manifest['rights'] = $rightsstatement->uri();
                $hasrights = true;
                break;
            }
        }
        if (!$hasrights) {
            foreach ($rights as $rightsstatement) {
                if (str_contains($rightsstatement->uri(), "rightsstatements.org")) {
                    $manifest['rights'] = $rightsstatement->uri();
                    break;
                }
            }
        }
        // Manifest homepages (this item is assigned to these sites).
        foreach ($item->sites() as $site) {
            if ($site->isPublic()) {
                $manifest['homepage'][] = [
                    'id' => $controller->url()->fromRoute('site/resource-id', ['site-slug' => $site->slug(), 'controller' => 'item', 'action' => 'show', 'id' => $item->id()], ['force_canonical' => true]),
                    'type' => 'Text',
                    'label' => [
                        'none' => [sprintf('Item in site: %s', $site->title())],
                    ],
                    'format' => 'text/html',
                ];
            }
        }

        if ($this->canvasTypeManager->has($renderer)) {
            $canvasType = $this->canvasTypeManager->get($renderer);
            // Compound objects return an array of canvases whereas other return just a single
            if ($renderer == 'remoteCompoundObject') {
                $canvases = $canvasType->getCanvas($media, $controller);
                foreach ($canvases as $canvas) {
                    // Allow modules to modify the canvas.
                    $args = $this->triggerEvent(
                        'iiif_presentation.3.media.canvas',
                        [
                            'canvas' => $canvas,
                            'canvas_type' => $canvasType,
                            'media' => $media,
                        ]
                    );
                    // Set the canvas to the manifest.
                    $manifest['items'][] = $args['canvas'];
                }
                // Search service for indexed material
                if ($mediaData['indexed']) {
                    $manifest["service"] = [
                        "@context" => "http://iiif.io/api/search/1/context.json",
                        "@id" => $url->fromRoute('iiif-search-1/media', ['media-id' => $mediaId], ['force_canonical' => true]),
                        "profile" => "http://iiif.io/api/search/1/search"
                    ];
                }
                // Compound object may have associated PDF file
                // if ($media->mediaData()['pdf']) {
                //     $presigned = $controller->viewHelpers()->get('s3presigned');
                //     $manifest["rendering"][] = ['id' => $presigned($media->mediaData()['pdf']), 'type' => 'Text', 'label' => ['en' => ['PDF version']], 'format' => 'application/pdf'];
                // }
            } else {
                $canvas = $canvasType->getCanvas($media, $controller);
                if ($canvas) {
                    // Allow modules to modify the canvas.
                    $args = $this->triggerEvent(
                        'iiif_presentation.3.media.canvas',
                        [
                            'canvas' => $canvas,
                            'canvas_type' => $canvasType,
                            'media' => $media,
                        ]
                    );
                    // Set the canvas to the manifest.
                    $manifest['items'][] = $args['canvas'];
                }
            }
        }
        // Allow modules to modify the manifest.
        $args = $this->triggerEvent(
            'iiif_presentation.3.item.manifest',
            [
                'manifest' => $manifest,
                'item' => $item,
            ]
        );
        return $args['manifest'];
    }

    /**
     * Get the metadata of an Omeka resource, formatted for IIIF Presentation.
     *
     * @see https://iiif.io/api/presentation/3.0/#metadata
     */
    public function getMetadata(AbstractResourceEntityRepresentation $resource)
    {
        $allValues = [];
        foreach ($resource->values() as $term => $propertyValues) {
            $label = $propertyValues['alternate_label'] ?? $propertyValues['property']->label();
            foreach ($propertyValues['values'] as $valueRep) {
                if (($term == "dcterms:identifier") && ($valueRep->type() == 'uri') && $valueRep->value()) {
                    $value = $valueRep->value() . ": " . $valueRep->uri();
                } else {
                    $value = $valueRep->value();
                }
                if (!is_string($value)) {
                    continue;
                }
                $lang = $valueRep->lang();
                if (!$lang) {
                    $lang = 'none';
                }
                $allValues[$label][$lang][] = $value;
            }
        }
        $metadata = [];
        foreach ($allValues as $label => $valueData) {
            $metadata[] = [
                'label' => ['none' => [$label]],
                'value' => $valueData,
            ];
        }
        return $metadata;
    }

    /**
     * Get a IIIF Presentation API response.
     *
     * @see https://iiif.io/api/presentation/3.0/#63-responses
     */
    public function getResponse(array $content)
    {
        $controller = $this->getController();
        $response = $controller->getResponse();
        $response->getHeaders()->addHeaders([
            'Content-Type' => 'application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=600, immutable',
            'Pragma' => '',
        ]);
        $response->setContent(json_encode($content, JSON_PRETTY_PRINT));
        return $response;
    }
}
