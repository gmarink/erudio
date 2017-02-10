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

namespace CalendarioBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Doctrine\ORM\QueryBuilder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use FOS\RestBundle\Controller\Annotations as FOS;
use FOS\RestBundle\Request\ParamFetcher;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use CoreBundle\REST\AbstractEntityResource;
use CalendarioBundle\Entity\AulaObservacao;

/**
 * @FOS\RouteResource("aula-observacoes")
 */
class AulaObservacaoController extends AbstractEntityResource {
    
    function getEntityClass() {
        return 'CalendarioBundle:AulaObservacao';
    }
    
    function queryAlias() {
        return 'aO';
    }
    
    function parameterMap() {
        return array (
            'observacao' => function(QueryBuilder $qb, $value) {
                $qb->andWhere('aO.observacao LIKE :observacao')->setParameter('observacao', '%' . $value . '%');
            },
            'aula' => function(QueryBuilder $qb, $value) {
                $qb->join('aO.aula', 'aula')
                   ->andWhere('aula.id = :aula')->setParameter('aula', $value);
            }
        );
    }
    
    /**
        *   @ApiDoc()
        */
    function getAction(Request $request, $id) {
        return $this->getOne($id);
    }
    
    /**
        *   @ApiDoc()
        * 
        *   @FOS\QueryParam(name = "page", requirements="\d+", default = null) 
        *   @FOS\QueryParam(name = "observacao", nullable = true) 
        *   @FOS\QueryParam(name = "aula", requirements="\d+", nullable = false) 
        */
    function cgetAction(Request $request, ParamFetcher $paramFetcher) {
        return $this->getList($paramFetcher);
    }
    
    /**
        *  @ApiDoc()
        * '
        *  @FOS\Post("aula-observacoes")
        *  @ParamConverter("aulaObservacao", converter="fos_rest.request_body")
        */
    function postAction(Request $request, AulaObservacao $aulaObservacao, ConstraintViolationListInterface $errors) {
        if(count($errors) > 0) {
            return $this->handleValidationErrors($errors);
        }
        return $this->create($aulaObservacao);
    }
    
    /**
        *  @ApiDoc()
        * 
        *  @FOS\Put("aula-observacoes/{id}")
        *  @ParamConverter("aulaObservacao", converter="fos_rest.request_body")
        */
    function putAction(Request $request, $id, AulaObservacao $aulaObservacoes, ConstraintViolationListInterface $errors) {
        if(count($errors) > 0) {
            return $this->handleValidationErrors($errors);
        }
        return $this->update($id, $aulaObservacoes);
    }
    
    /**
        *   @ApiDoc()
        */
    function deleteAction(Request $request, $id) {
        return $this->remove($id);
    }

}
