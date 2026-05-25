<?php

namespace Simp\Pindrop\Modules\wiki\src\Controller;

use Psr\Container\ContainerInterface;
use Simp\Pindrop\Controller\ControllerBase;
use Simp\Pindrop\Entity\User\CurrentUser;
use Simp\Pindrop\Message\Message;
use Simp\Pindrop\Modules\wiki\src\Plugin\Events\Events;
use Simp\Pindrop\Modules\wiki\src\Service\WikiService;
use Simp\Pindrop\Routing\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class WikiController extends ControllerBase
{
    
    public function __construct(protected WikiService $wikiService)
    {
        return parent::__construct();
    }

    public static function create(ContainerInterface $container) {
        return new static($container->get(WikiService::class));
    }


    public function add(Request $request, string $route_name, array $options)
    {
        if ($request->getMethod() === "POST") {
            $wiki  = $request->request->all();
            $wiki['author_id'] = getAppContainer()->get('current_user')->id();
            if ($this->wikiService->create($wiki)){
                Message::info("Wiki entry added sucessfully");
                return $this->redirect(Url::routeByName("wiki.list"));
            }
        }
        return $this->renderTwig("@wiki/forms/create_entry.html.twig",[
            "page_title" => "Add Wiki Entry",
        ]);
    }

    public function list(Request $request, string $route_name, array $options)
    {
        $wikis = $this->wikiService->findByAuthor(getAppContainer()->get("current_user")->id());
        return $this->renderTwig("@wiki/list.html.twig",[
            'page_title'=>'Wiki Pages',
            'wiki_pages' => $wikis,
        ]);
    }

    public function view(Request $request, string $route_name, array $options)
    {
        $slug = $request->query->get('slug');
        $wiki = $this->wikiService->findBySlug($slug);

        $templateName = "@wiki/view/view.html.twig";
        $overrideName = appEvents()->invokeEvents(Events::WIKI_VIEW_TEMPALATE,['template'=> $templateName]);
       
        if (!empty($overrideName['template'])) {
            $templateName = $overrideName['template'];
        }

        $wiki['content'] = json_decode($wiki['content'] ?? "", true);
        
        return $this->renderTwig($templateName,[
            'wiki' => $wiki,
            'page_title'=> $wiki['title']
        ]);
    }

    public function wikiEditorSaver(Request $request, string $route_name, array $options)
    {
        $content = json_decode($request->getContent(), true);
        $current_user = getAppContainer()->get('current_user');

        if ($current_user instanceof CurrentUser) {
            $user = $current_user->getUser();

            if ($user->isAdmin() || $user->isModerator()){

                $toc = $content['toc'] ?? null;
                $ct  = $content['ct'] ?? null;
                $id  = $content['id'] ?? null;

                if ($toc && $ct && $id) {
                    $wiki = $this->wikiService->find($id);
                    $wiki['content'] = json_decode($wiki['content'] ?? "", true);
                    $savable = ['ct'=> $ct, 'toc'=> $toc, 'refs' => $wiki['content']['refs'] ?? []];
                   
                    $result = $this->wikiService->update($id,[
                        'content' => json_encode($savable),
                        'id'      => $id
                    ]);

                    return new JsonResponse(['status'=> $result]);
                }
            }
        }


        return new JsonResponse(['status'=> false]);
    }

    public function wikiEditorSaverRefs(Request $request, string $route_name, array $options)
    {
        $content = json_decode($request->getContent(), true);
        $current_user = getAppContainer()->get('current_user');

        if ($current_user instanceof CurrentUser) {
            $user = $current_user->getUser();

            if ($user->isAdmin() || $user->isModerator()){

                $refs = $content['refs'] ?? null;
                $id  = $content['id'] ?? null;

                if ($refs && $id) {
                    $wiki = $this->wikiService->find($id);
                    $wiki['content'] = json_decode($wiki['content'] ?? "", true);
                    $wiki['content']['refs'] = $refs;
                
                    $result = $this->wikiService->update($id,[
                        'content' => json_encode($wiki['content']),
                        'id'      => $id
                    ]);

                    return new JsonResponse(['status'=> $result]);
                }
            }
        }


        return new JsonResponse(['status'=> false]);
    }
}
