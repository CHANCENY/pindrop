<?php

namespace Simp\Pindrop\Modules\wiki\src\Plugin\Twig;

use Simp\Pindrop\Entity\User\CurrentUser;
use Simp\Pindrop\Modules\wiki\src\Service\WikiService;
use Simp\Pindrop\Routing\Url;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;


class TwigWikiExtension extends AbstractExtension
{
    protected WikiService $wikiService;

    public function __construct()
    {
        $this->wikiService = getAppContainer()->get(WikiService::class);
        
    }
    
    public function getFunctions()
    {
        return [
            new TwigFunction('wiki_page_url', [$this, 'getWikiPageUrl']),
            new TwigFunction('is_allowed_to_edit_wiki_page', [$this, 'isAllowedToEditWikiPage']),
        ];
    }

    public function getWikiPageUrl(int $wikiId)
    {
        if (!$wikiId) {
            return null;
        }

        $wikiPage = $this->wikiService->find($wikiId);

        if (!$wikiPage) {
            return null;
        }

        return Url::routeByName('wiki.view', ['slug' => $wikiPage['slug']]);
    }

    public function isAllowedToEditWikiPage(int $wikiId)
    {
        if (!$wikiId) {
            return false;
        }

        $wikiPage = $this->wikiService->find($wikiId);

        if (!$wikiPage) {
            return false;
        }

        $currentUser = getAppContainer()->get('current_user');
        if (!$currentUser) {
            return false;
        }

        if ($currentUser instanceof CurrentUser){
            
            if ($currentUser->id() === $wikiPage['author_id']) {
                return true;
            }

            // check if user has permission to edit wiki pages
            if ($currentUser->getUser()->hasPermission('can_edit_any_page')) {
                return true;
            }
        }

        return false;
    }
}