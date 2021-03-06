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

namespace MatriculaBundle\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use CoreBundle\ORM\AbstractFacade;
use CoreBundle\ORM\Exception\IllegalOperationException;
use AvaliacaoBundle\Entity\SistemaAvaliacao;

class MediaFacade extends AbstractFacade {
    
    public function getEntityClass() {
        return 'MatriculaBundle:Media';
    }
    
    function queryAlias() {
        return 'm';
    }
    
    function parameterMap() {
        return array (
            'disciplinaCursada' => function(QueryBuilder $qb, $value) {
                $qb->join('m.disciplinaCursada', 'disciplinaCursada')
                    ->andWhere('disciplinaCursada.id = :disciplinaCursada')
                    ->setParameter('disciplinaCursada', $value);
            }
        );
    }
    
    function calcular($media) {
        $sistemaAvaliacao = $media->getDisciplinaCursada()->getDisciplina()->getEtapa()->getSistemaAvaliacao();
        $valor = null;
        switch($sistemaAvaliacao->getTipo()) {
            case SistemaAvaliacao::TIPO_QUANTITATIVO:
                $valor = $this->calcularMediaPonderada($media->getNotas());
                break;
            case SistemaAvaliacao::TIPO_QUALITATIVO:
                $valor = $this->calcularMediaConceitual($media->getNotas());
                break;
        }
        $media->setValor($valor);
        return $media;
    }

    function calcularMediaPonderada($notas) {
        $valor = $peso = 0.00;
        foreach($notas as $nota) {
            $peso += $nota->getAvaliacao()->getPeso();
            $valor += $nota->getValor() * (float)$nota->getAvaliacao()->getPeso();
        }        
        return $valor / (float) $peso;
    }
    
    function calcularMediaConceitual($notas) {
        $valor = 0.00;
        $notaFechamento = $notas->filter(function($n) { return $n->getFechamentoMedia(); })->first();
        if(!$notaFechamento) {
            throw new IllegalOperationException('Não existem notas de fechamento para a média');
        }
        $habilidades = $notaFechamento->getHabilidadesAvaliadas();
        $numHabilidades = count($habilidades);
        foreach($habilidades as $habilidade) {            
            $conceito = $habilidade->getConceito();
            $conceitos[] = $conceito->getId();
            if($conceito->getValorMaximo() == 0 && $conceito->getValorMinimo() == 0) {
                $numHabilidades--;
            }
        }
        $countConceitos = array_count_values($conceitos);        
        foreach($habilidades as $habilidade) {            
            $valor += ($countConceitos[$conceito->getId()] >= $numHabilidades / 2)
                ? $habilidade->getConceito()->getValorMaximo() 
                : $habilidade->getConceito()->getValorMinimo();
        }        
        return ($numHabilidades > 0) ? $valor / $numHabilidades : 0.00;
    }
    
    protected function afterUpdate($media) {
        if($media->getCalculoAutomatico()) {
            $this->calcular($media);
        }
        $this->orm->getManager()->flush();
    }

}

