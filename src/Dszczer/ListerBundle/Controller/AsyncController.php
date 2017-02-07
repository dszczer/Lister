<?php
/**
 * Default controller to handle list with JavaScript.
 * @category     Controllers
 * @author       Damian Szczerbiński <dszczer@gmail.com>
 */

namespace Dszczer\ListerBundle\Controller;

use Dszczer\ListerBundle\Lister\Lister;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AsyncController
 * @package Dszczer\ListerBundle\Controller
 */
class AsyncController extends Controller
{
    /**
     * Action for lightweight list sorting, paginating and filtering.
     * @param Request $request Request to handle.
     * @param string $uuid Identifier of the list.
     * @return JsonResponse Response to handle by JavaScript.
     */
    public function quickAction(Request $request, $uuid)
    {
        try {
            if (!Lister::getFromSession($request->getSession(), $uuid) instanceof Lister) {
                throw new \RuntimeException("$uuid is not a persisted list");
            }
            /** @var Lister $list */
            $list = $this->get('lister.factory')->createList('', $uuid);
            $list->apply($request);
            $pager = $list->getPager();
            $result = [];
            foreach ($pager->getResults() as $object) {
                if ($object instanceof ActiveRecordInterface) {
                    $data = [];
                    foreach ($list->getHydratedElements($object) as $element) {
                        $data[$element->getName()] = $element->getData();
                    }
                    $result[] = $data;
                }
            }

            $response = new JsonResponse([
                'id' => $list->getId(),
                'result' => $result,
                'resultCount' => count($result),
                'firstPage' => 1,
                'lastPage' => $pager->getLastPage(),
                'currentPage' => $pager->getPage(),
                'listHTML' => $this->callTwigFunction('lister_body', [$list]),
                'filterHTML' => $this->callTwigFunction('lister_filters', [$list]),
                'paginationHTML' => $this->callTwigFunction('lister_pagination', [$list]),
                'status' => [
                    'type' => 'OK',
                    'message' => '',
                ],
            ]);
        } catch (\Throwable $throwable) {
            $response = new JsonResponse([
                'id' => $uuid,
                'status' => [
                    'type' => 'ERROR',
                    'message' => $throwable->getMessage(),
                ],
            ], 500);
        } finally {
            $response->setCache([
                'max_age' => 0,
                's_maxage' => 0,
                'public' => false,
                'private' => true
            ]);

            return $response;
        }
    }

    /**
     * Helper to call loaded Twig function.
     * @param string $name Twig name of the function
     * @param array $argv Array of arguments to pass
     * @return mixed Value returned by called function
     * @throws \Twig_Error_Runtime Twig error
     */
    private function callTwigFunction($name, array $argv)
    {
        $env = $this->get('twig');
        /** @var \Twig_SimpleFunction $function */
        $function = $env->getFunction($name);

        if (!$function) {
            throw new \Twig_Error_Runtime("Function $name does not exist in Twig");
        }
        if ($function->needsContext()) {
            array_unshift($argv, null);
        }
        if ($function->needsEnvironment()) {
            array_unshift($argv, $env);
        }

        return call_user_func_array($function->getCallable(), $argv);
    }
}