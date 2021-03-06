<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *    @author Municipio de Itajaí - Secretaria de Educação - DITEC         *
 *    @updated 30/06/2016                                                  *
 *    Pacote: Erudio                                                       *
 *                                                                         *
 *    Copyright (C) 2016 Prefeitura de Itajaí - Secretaria de Educação     *
 *                       DITEC - Diretoria de Tecnologias educacionais     *
 *                        ditec@itajai.sc.gov.br                           *
 *                                                                         *
 *    Este  programa  é  software livre, você pode redistribuí-lo e/ou     *
 *    modificá-lo sob os termos da Licença Pública Geral GNU, conforme     *
 *    publicada pela Free  Software  Foundation,  tanto  a versão 2 da     *
 *    Licença   como  (a  seu  critério)  qualquer  versão  mais  nova.    *
 *                                                                         *
 *    Este programa  é distribuído na expectativa de ser útil, mas SEM     *
 *    QUALQUER GARANTIA. Sem mesmo a garantia implícita de COMERCIALI-     *
 *    ZAÇÃO  ou  de ADEQUAÇÃO A QUALQUER PROPÓSITO EM PARTICULAR. Con-     *
 *    sulte  a  Licença  Pública  Geral  GNU para obter mais detalhes.     *
 *                                                                         *
 *    Você  deve  ter  recebido uma cópia da Licença Pública Geral GNU     *
 *    junto  com  este  programa. Se não, escreva para a Free Software     *
 *    Foundation,  Inc.,  59  Temple  Place,  Suite  330,  Boston,  MA     *
 *    02111-1307, USA.                                                     *
 *                                                                         *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

namespace CoreBundle\REST;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\NoResultException;
use FOS\RestBundle\View\ViewHandlerInterface;
use FOS\RestBundle\View\View;
use JMS\Serializer\Annotation as JMS;
use CoreBundle\ORM\AbstractFacade;
use CoreBundle\Exception\PublishedException;

/**
* Controlador REST que serve como base aos demais.
* Oferece os métodos CRUD já implementados, usando a classe Facade retornada pelo método getFacade.
*/
abstract class AbstractEntityController extends Controller {
    
    const SERIALIZER_GROUP_LIST = 'LIST';
    const SERIALIZER_GROUP_DETAILS = 'DETAILS';
    const SERIALIZER_DEFAULT_GROUPS = ['LIST', 'DETAILS'];
    const SERIALIZER_MAX_DEPTH = 4;
    
    const LINK_HEADER = 'Link';
    const PAGE_PARAM = 'page';
    const VIEW_PARAM = 'view';
    
    private $entityFacade;
    private $viewHandler;
    
    function __construct(AbstractFacade $entityFacade, ViewHandlerInterface $viewHandler = null) {
        $this->entityFacade = $entityFacade;
        $this->viewHandler = $viewHandler;
    }
    
    function setViewHandler(ViewHandlerInterface $viewHandler) {
        $this->viewHandler = $viewHandler;
    }
    
    function getFacade() {
        return $this->entityFacade;
    }
    
    function getOne(Request $request, $id, $viewGroup = null) {
        $viewGroups = self::SERIALIZER_DEFAULT_GROUPS;
        if ($viewGroup) {
            $viewGroups[] = $viewGroup;
        }
        try {
            $entidade = $this->getFacade()->find($id);
            $view = View::create($entidade, Response::HTTP_OK);
            $this->configureContext($view->getContext(), $viewGroups);
        } catch(NoResultException $ex) {
            throw new NotFoundHttpException();
        }
        return $this->handleView($view);
    }

    function getList(Request $request, array $params) {
        $page = key_exists(self::PAGE_PARAM, $params) ? $params[self::PAGE_PARAM] : null;
        $viewGroups = key_exists(self::VIEW_PARAM, $params) 
                ? [self::SERIALIZER_GROUP_LIST, $params[self::VIEW_PARAM]]
                : [self::SERIALIZER_GROUP_LIST];
        $resultados = $this->getFacade()->findAll($params, $page);
        $view = View::create($resultados, Response::HTTP_OK);
        if (is_numeric($page)) {
            $this->addPageLinks($request, $view, $params, $page);
        }
        $this->configureContext($view->getContext(), $viewGroups);
        return $this->handleView($view);
    }
    
    function post(Request $request, $entidade, ConstraintViolationListInterface $errors) {
        if(count($errors) > 0) {
            return $this->handleValidationErrors($errors);
        }
        $entidadeCriada = $this->getFacade()->create($entidade);
        $view = View::create($entidadeCriada, Response::HTTP_OK);
        $this->configureContext($view->getContext());
        return $this->handleView($view);
    }
    
    function postBatch(Request $request, $entidades, ConstraintViolationListInterface $errors) {
        if(count($errors) > 0) {
            return $this->handleValidationErrors($errors);
        }
        $this->getFacade()->createBatch($entidades);
        $view = View::create(null, Response::HTTP_NO_CONTENT);
        $this->configureContext($view->getContext());
        return $this->handleView($view);
    }
    
    function put(Request $request, $id, $entidade, ConstraintViolationListInterface $errors) {
        if(count($errors) > 0) {
            return $this->handleValidationErrors($errors);
        }
        $entidadeAtualizada = $this->getFacade()->patch($id, $entidade);
        $view = View::create($entidadeAtualizada, Response::HTTP_OK);
        $this->configureContext($view->getContext());
        return $this->handleView($view);
    }
    
    function putBatch(Request $request, $entidades, ConstraintViolationListInterface $errors) {
        if(count($errors) > 0) {
            return $this->handleValidationErrors($errors);
        }
        $this->getFacade()->patchBatch($entidades);
        $view = View::create(null, Response::HTTP_NO_CONTENT);
        $this->configureContext($view->getContext());
        return $this->handleView($view);
    }
    
    function delete(Request $request, $id) {
        try {
            $this->getFacade()->remove($id);
            $view = View::create(null, Response::HTTP_NO_CONTENT);
        } catch(NoResultException $ex) {
            throw new NotFoundHttpException();
        }
        return $this->handleView($view);
    }
    
    protected function addPageLinks(Request $request, View $view, array $params, $page) {
        $links = [];
        $paginasRestantes = $this->getFacade()->count($params) / (($page + 1) * AbstractFacade::PAGE_SIZE);
        if ($paginasRestantes > 1) {
            $next = $page + 1;
            $links[] = "<{$request->getPathInfo()}?page={$next}>; rel=\"next\"";
        }
        if ($page > 0) {
            $prev = $page - 1;
            $links[] = "<{$request->getPathInfo()}?page={$prev}>; rel=\"previous\"";
        }
        $view->setHeader(self::LINK_HEADER, $links);
    }
    
    protected function handleValidationErrors(ConstraintViolationListInterface $errors) {
        throw new PublishedException($errors[0]->getMessage());
    }
    
    protected function configureContext($context, array $viewGroups = null) {
        $context->setGroups($viewGroups ? $viewGroups : [self::SERIALIZER_GROUP_LIST, self::SERIALIZER_GROUP_DETAILS]);
        $context->setMaxDepth(self::SERIALIZER_MAX_DEPTH);
        return $context;
    }
    
    protected function handleView(View $view) {
        return $this->viewHandler->handle($view);
    }
    
}
