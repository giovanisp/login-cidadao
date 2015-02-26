<?php

namespace PROCERGS\OAuthBundle\Entity;

use Doctrine\ORM\EntityRepository;
use PROCERGS\LoginCidadao\CoreBundle\Model\PersonInterface;

class ClientRepository extends EntityRepository
{

    public function findOneOwned(PersonInterface $person, $id)
    {
        return $this->createQueryBuilder('c')
                ->where(':person MEMBER OF c.owners')
                ->andWhere('c.id = :id ')
                ->setParameters(compact('person', 'id'))
                ->getQuery()
                ->getOneOrNullResult();
    }

    public function getCountPerson() {
      $qb = $this->getEntityManager()->createQueryBuilder();

      return $qb
              ->select('count(a.id) AS qty, c AS client')
              ->from('PROCERGSLoginCidadaoCoreBundle:Authorization', 'a')
              ->innerJoin('PROCERGSOAuthBundle:Client', 'c', 'WITH', 'a.client = c')
              ->where('c.published = true')
              ->groupBy('a.client, c')
              ->orderBy('qty', 'DESC')
              ->getQuery()->getResult();
    }

}
