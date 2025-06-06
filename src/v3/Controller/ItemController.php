<?php

namespace IiifPresentation\v3\Controller;

use Laminas\Mvc\Controller\AbstractActionController;

class ItemController extends AbstractActionController
{
    public function viewCollectionAction()
    {
        $url = $this->url()->fromRoute('iiif-presentation-3/item/collection', [], ['force_canonical' => true], true);
        return $this->redirect()->toRoute('iiif-viewer', [], ['query' => ['url' => $url]]);
    }

    public function collectionAction()
    {
        $itemIds = explode(',', $this->params('item-ids'));
        $collection = $this->iiifPresentation3()->getItemsCollection($itemIds, $this->translate('Items Collection'));
        return $this->iiifPresentation3()->getResponse($collection);
    }

    public function viewManifestAction()
    {
        $url = $this->url()->fromRoute('iiif-presentation-3/item/manifest', [], ['force_canonical' => true], true);
        return $this->redirect()->toRoute('iiif-viewer', [], ['query' => ['url' => $url]]);
    }

    public function manifestAction()
    {
        $itemId = $this->params('item-id');
        $manifest = $this->iiifPresentation3()->getItemManifest($itemId);
        return $this->iiifPresentation3()->getResponse($manifest);
    }
    public function mediaManifestAction()
    {
        $mediaId = $this->params('media-id');
        if ($media = $this->api()->read('media', $mediaId)->getContent()) {
            $manifest = $this->iiifPresentation3()->getMediaManifest($media->item()->id(), $mediaId);
            return $this->iiifPresentation3()->getResponse($manifest);
        }
    }
}
