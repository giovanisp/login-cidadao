<?php

namespace PROCERGS\LoginCidadao\CoreBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use PROCERGS\OAuthBundle\Entity\Client;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * @ORM\Entity
 * @ExclusionPolicy("all")
 */
class Person extends BaseUser
{

    /**
     * @Expose
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @Expose
     * @Groups({"name"})
     * @ORM\Column(type="string")
     * @Assert\NotBlank(message="Please enter your name.", groups={"Registration", "Profile"})
     * @Assert\Length(
     *     min=3,
     *     max="255",
     *     minMessage="The name is too short.",
     *     maxMessage="The name is too long.",
     *     groups={"Registration", "Profile"}
     * )
     */
    protected $name;

    /**
     * @Expose
     * @Groups({"cpf"})
     */
    protected $username;

    /**
     * @Expose
     * @Groups({"email"})
     */
    protected $email;

    /**
     * @Expose
     * @Groups({"birthdate"})
     * @ORM\Column(type="date")
     */
    protected $birthdate;

    /**
     * @Expose
     * @Groups({"cep"})
     * @ORM\Column(type="string", nullable=true)
     */
    protected $cep;

    /**
     * @Expose
     * @Groups({"city"})
     * @ORM\ManyToOne(targetEntity="PROCERGS\LoginCidadao\CoreBundle\Entity\City")
     * @ORM\JoinColumn(name="city_id", referencedColumnName="id")
     */
    protected $city;

    /**
     * @var string
     *
     * @ORM\Column(name="facebookId", type="string", length=255, nullable=true)
     */
    protected $facebookId;

    /**
     * @ORM\OneToMany(targetEntity="Authorization", mappedBy="person", cascade={"remove"}, orphanRemoval=true)
     */
    protected $authorizations;

    public function __construct()
    {
        parent::__construct();
        $this->authorizations = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getBirthdate()
    {
        return $this->birthdate;
    }

    public function setBirthdate($birthdate)
    {
        $this->birthdate = $birthdate;
    }

    public function getCep()
    {
        return $this->cep;
    }

    public function setCep($cep)
    {
        $this->cep = $cep;
    }

    public function addAuthorization(Authorization $authorization)
    {
        $this->authorizations->add($authorization);
        $authorization->setPerson($this);
        return $this;
    }

    public function removeAuthorization(Authorization $authorization)
    {
        if ($this->authorizations->contains($authorization)) {
            $this->authorizations->removeElement($authorization);
        }
        return $this;
    }

    public function getAuthorizations()
    {
        return $this->authorizations;
    }

    /**
     * Checks if a given Client can access this Person's specified scope.
     * @param \PROCERGS\OAuthBundle\Entity\Client $client
     * @param mixed $scope can be a single scope or an array with several.
     * @return boolean
     */
    public function isAuthorizedClient(Client $client, $scope)
    {
        $authorizations = $this->getAuthorizations();
        foreach ($authorizations as $auth) {
            $c = $auth->getClient();
            if ($c->getId() == $client->getId()) {
                return $auth->hasScopes($scope);
            }
        }
        return false;
    }

    /**
     * Checks if this Person has any authorization for a given Client.
     * WARNING: Note that it does NOT validate scope!
     * @param \PROCERGS\OAuthBundle\Entity\Client $client
     */
    public function hasAuthorization(Client $client)
    {
        $authorizations = $this->getAuthorizations();
        foreach ($authorizations as $auth) {
            $c = $auth->getClient();
            if ($c->getId() == $client->getId()) {
                return true;
            }
        }
        return false;
    }

    public function getFirstName()
    {
        $exploded = explode(' ', $this->getName());
        $firstName = reset($exploded);
        return $firstName;
    }

    public function setFacebookId($facebookId)
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    public function getFacebookId()
    {
        return $this->facebookId;
    }

    public function serialize()
    {
        return serialize(array($this->facebookId, parent::serialize()));
    }

    public function unserialize($data)
    {
        list($this->facebookId, $parentData) = unserialize($data);
        parent::unserialize($parentData);
    }

    /**
     * Get the full name of the user (first + last name)
     * @return string
     */
    public function getFullName()
    {
        return $this->getFirstname() . ' ' . $this->getLastname();
    }

    /**
     * @param array
     */
    public function setFBData($fbdata)
    {
        if (isset($fbdata['id'])) {
            $this->setFacebookId($fbdata['id']);
            $this->addRole('ROLE_FACEBOOK');
        }
        if (isset($fbdata['first_name'])) {
            $this->setFirstname($fbdata['first_name']);
        }
        if (isset($fbdata['last_name'])) {
            $this->setSurname($fbdata['last_name']);
        }
        if (isset($fbdata['email'])) {
            $this->setEmail($fbdata['email']);
        }
    }

}
