<?php

namespace PROCERGS\LoginCidadao\APIBundle\Controller;

use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\Annotations as REST;
use Symfony\Component\HttpFoundation\Request;
use PROCERGS\LoginCidadao\CoreBundle\Entity\Country;
use PROCERGS\LoginCidadao\CoreBundle\Entity\State;
use PROCERGS\LoginCidadao\CoreBundle\Entity\City;
use PROCERGS\LoginCidadao\APIBundle\Security\Audit\Annotation as Audit;
use Doctrine\ORM\Query;

class AddressController extends FOSRestController
{

    /**
     * @REST\Get("/public/postalcode/{postalCode}", name="lc_consultaCep2", defaults={"postalCode" = ""})
     * @REST\View()
     * @Audit\Loggable(type="SELECT")
     */
    public function viewPostalCodeAction($postalCode)
    {
        $request = $this->getRequest();
        $busca = $this->get('procergs_logincidadao.dne');
        $postalCodes = $busca->findByCep($postalCode);
        if ($postalCodes) {
            $result = array(
                'code' => 200,
                'msg' => null,
                'items' => array(
                    $postalCodes
                )
            );
        } else {
            throw new NotFoundHttpException();
        }

        $view = $this->view($result);
        return $this->handleView($view);
    }

    /**
     * @REST\Get("/public/country", name="lc_search_country" )
     * @REST\View()
     * @Audit\Loggable(type="SELECT")
     */
    public function searchCountryAction()
    {
        $request = $this->getRequest();
        $query = $this->getDoctrine()->getRepository('PROCERGSLoginCidadaoCoreBundle:Country')
        ->createQueryBuilder('cty')
        ->where('cty.reviewed = ' . Country::REVIEWED_OK)
        ->orderBy('cty.id', 'ASC');
        return $this->handleView($this->view($query->getQuery()->getResult(Query::HYDRATE_ARRAY)));
    }

    /**
     * @REST\Get("/public/state", name="lc_search_state" )
     * @REST\View()
     * @Audit\Loggable(type="SELECT")
     */
    public function searchStateAction(Request $request = null)
    {
        $request = $this->getRequest();
        $query = $this->getDoctrine()->getRepository('PROCERGSLoginCidadaoCoreBundle:State')
        ->createQueryBuilder('state')
        ->where('state.reviewed = ' . Country::REVIEWED_OK);
        $country = $request->get('country_id');
        if ($country) {
            $query->join('PROCERGSLoginCidadaoCoreBundle:Country', 'cty', 'WITH', 'state.country = cty');
            $query->andWhere('cty.id = :country');
            $query->setParameter('country', $country);
        }
        $query->orderBy('state.id', 'ASC');
        return $this->handleView($this->view($query->getQuery()->getResult(Query::HYDRATE_ARRAY)));
    }

    /**
     * @REST\Get("/public/city", name="lc_search_city" )
     * @REST\View()
     * @Audit\Loggable(type="SELECT")
     */
    public function searchCityAction(Request $request = null)
    {
        $request = $this->getRequest();
        $query = $this->getDoctrine()->getRepository('PROCERGSLoginCidadaoCoreBundle:City')
        ->createQueryBuilder('c')
        ->where('c.reviewed = ' . Country::REVIEWED_OK);
        $country = $request->get('country_id');
        $state = $request->get('state_id');
        if ($country || $state) {
            $query->join('PROCERGSLoginCidadaoCoreBundle:State', 'state', 'WITH', 'c.state = state');
        }
        if ($country) {
            $query->join('PROCERGSLoginCidadaoCoreBundle:Country', 'cty', 'WITH', 'state.country = cty');
            $query->andWhere('cty.id = :country');
            $query->setParameter('country', $country);
        }
        if ($state) {
            $query->andWhere('state.id = :state');
            $query->setParameter('state', $state);
        }
        $query->orderBy('c.id', 'ASC');
        return $this->handleView($this->view($query->getQuery()->getResult(Query::HYDRATE_ARRAY)));
    }

}
