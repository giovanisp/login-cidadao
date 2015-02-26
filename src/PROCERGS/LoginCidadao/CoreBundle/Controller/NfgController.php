<?php
namespace PROCERGS\LoginCidadao\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use PROCERGS\LoginCidadao\CoreBundle\Form\Type\ContactFormType;
use PROCERGS\LoginCidadao\CoreBundle\Entity\SentEmail;
use PROCERGS\LoginCidadao\CoreBundle\Entity\State;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use PROCERGS\LoginCidadao\CoreBundle\Exception\NfgException;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use PROCERGS\LoginCidadao\CoreBundle\Entity\NfgProfile;
use PROCERGS\LoginCidadao\NotificationBundle\Entity\Notification;

/**
 * @Route("/nfg")
 */
class NfgController extends Controller
{

    protected function toNfg($url, $callback, $useSession = false)
    {
        $nfg = $this->get('procergs_logincidadao.nfgws');
        $parm['accessid'] = $nfg->obterAccessID();
        if ($useSession) {
            $this->getRequest()
                ->getSession()
                ->set('ticketacessologin', $parm['accessid']);
        }
        $parm['urlretorno'] = $this->generateUrl($callback, array(), UrlGeneratorInterface::ABSOLUTE_URL);
        // $url = $this->container->getParameter('nfg_url_auth') . '?' . http_build_query($parm);
        $url = $this->container->getParameter($url) . '?accessid=' . $parm['accessid'] . '&urlretorno=' . $parm['urlretorno'];
        //IE referer stuff, dont kill me
        return new Response('<html><head><meta name="referrer" content="always"/></head><body><script type="text/javascript">document.location= "'.$url.'";</script></body></html>');
    }

    protected function checkAccessToken($voterRegistration = NULL)
    {
        $request = $this->getRequest();
        $paccessid = $request->get('paccessid');
        if (! $paccessid) {
            throw new NfgException('nfg.missing.token');
        }
        $nfg = $this->get('procergs_logincidadao.nfgws');
        $nfg->setAccessToken($paccessid);
        if ($voterRegistration) {
            $nfg->setTituloEleitoral($voterRegistration);
        }
        $result1 = $nfg->consultaCadastro();
        if ($result1['CodSitRetorno'] != 1) {
            throw new NfgException($result1['MsgRetorno']);
        }
        if (! isset($result1['CodCpf'], $result1['NomeConsumidor'], $result1['EmailPrinc'])) {
            throw new NfgException('nfg.missing.required.fields');
        }
        $result1['paccessid'] = $paccessid;
        return $result1;
    }

    protected function checkOtherPerson(&$result1, &$em, &$personRepo)
    {
        $otherPerson = $personRepo->findOneBy(array(
            'cpf' => $result1['CodCpf']
        ));
        if ($otherPerson) {
            if ($otherPerson->getNfgAccessToken()) {
                $otherPersonNfg = $otherPerson->getNfgProfile();
                if ($otherPersonNfg->getAccessLvl() == 1) {
                    if ($result1['CodNivelAcesso'] == 1) {
                        throw new NfgException('notification.nfg.already.bind.but.weak', NfgException::E_BIND);
                    } else {
                        $otherPerson->setCpf(null);
                        $otherPerson->setNfgAccessToken(null);
                        $otherPerson->setNfgProfile(null);
                        //@TODO do no use updateUser
                        $this->container->get('fos_user.user_manager')->updateUser($otherPerson);
                        $this->container->get('notifications.helper')->revokedCpfNotification($otherPerson);
                    }
                } else {
                    throw new NfgException('notification.nfg.already.bind', NfgException::E_BIND);
                }
            } else {
                if ($result1['CodNivelAcesso'] == 1) {
                    throw new NfgException('notification.nfg.already.cpf.but.weak', NfgException::E_BIND);
                } else {
                    $otherPerson->setCpf(null);
                    //@TODO do no use updateUser
                    $this->container->get('fos_user.user_manager')->updateUser($otherPerson);
                    $this->container->get('notifications.helper')->revokedCpfNotification($otherPerson);
                }
            }
        } else {
            // the champion
        }
    }

    /**
     * @Route("/create", name="nfg_create")
     */
    public function createAction()
    {
        return $this->toNfg('nfg_url_auth', 'nfg_createback');
    }

    /**
     * @Route("/create/back", name="nfg_createback")
     */
    public function createBackAction(Request $request)
    {
        $result1 = $this->checkAccessToken();
        $em = $this->getDoctrine()->getManager();
        $personRepo = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person');
        if ($personRepo->findOneBy(array(
            'cpf' => $result1['CodCpf']
        ))) {
            throw new NfgException('nfg.cpf.already.used');
        }
        if ($personRepo->findOneBy(array(
            'email' => $result1['EmailPrinc']
        ))) {
            throw new NfgException('nfg.email.already.used');
        }

        $formFactory = $this->container->get('fos_user.registration.form.factory');
        $userManager = $this->container->get('fos_user.user_manager');
        $dispatcher = $this->container->get('event_dispatcher');

        $nfgProfile = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:NfgProfile')->findOneBy(array(
            'cpf' => $result1['CodCpf']
        ));
        if (! $nfgProfile) {
            $nfgProfile = new NfgProfile();
            $nfgProfile->setCpf($result1['CodCpf']);
        }
        $nfgProfile->setName($result1['NomeConsumidor']);
        $nfgProfile->setEmail($result1['EmailPrinc']);

        $user = $userManager->createUser();
        $user->setEnabled(true);
        $user->setPassword('');
        $user->setEmailConfirmedAt(new \DateTime());
        $user->setEmailExpiration(null);
        $user->setNfgAccessToken($result1['paccessid']);
        $user->setCpf($result1['CodCpf']);
        $user->setEmail($result1['EmailPrinc']);
        if ($result1['DtNasc']) {
            $user->setBirthdate(new \DateTime(str_replace('T', ' ', $result1['DtNasc'])));
            $nfgProfile->setBirthdate($user->getBirthdate());
        }
        if (isset($result1['NroFoneContato'])) {
            $user->setMobile($result1['NroFoneContato']);
            $nfgProfile->setMobile($user->getMobile());
        }
        if ($result1['CodNivelAcesso']) {
            $nfgProfile->setAccessLvl($result1['CodNivelAcesso']);
        }
        $nome = explode(' ', $result1['NomeConsumidor']);
        $user->setFirstName(array_shift($nome));
        $user->setSurname(implode(' ', $nome));
        $em->persist($nfgProfile);
        $user->setNfgProfile($nfgProfile);

        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_INITIALIZE, $event);

        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }

        $form = $formFactory->createForm();
        $form->setData($user);

        $event = new FormEvent($form, $request);
        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_SUCCESS, $event);

        $userManager->updateUser($user);

        if (null === $response = $event->getResponse()) {
            $url = $this->container->get('router')->generate('fos_user_registration_confirmed');
            $response = new RedirectResponse($url);
        }

        $dispatcher->dispatch(FOSUserEvents::REGISTRATION_COMPLETED, new FilterUserResponseEvent($user, $request, $response));

        return $response;
    }

    /**
     * @Route("/login", name="nfg_login")
     */
    public function loginAction()
    {
        return $this->toNfg('nfg_url_login', 'nfg_loginback', true);
    }

    /**
     * @Route("/login/back", name="nfg_loginback")
     */
    public function loginBacktAction(Request $request)
    {
        $cpf = $request->get('cpf');
        $accessid = $request->get('accessid');
        $prsec = $request->get('prsec');
        if (null == $accessid || null == $cpf || null == $prsec) {
            throw new NfgException('nfg.corrupted.callback');
        }
        $sig = hash_hmac('sha256', "$cpf$accessid", $this->container->getParameter('nfg_hmac_secret'));
        if (false == $sig || strcmp(strtoupper($sig), $prsec) !== 0) {
            throw new NfgException('nfg.corrupted.callback');
        }
        if ($request->getSession()->get('ticketacessologin') != $accessid) {
            throw new NfgException('nfg.accessid.mismatch');
        }
        $cpf = str_pad($cpf, 11, "0", STR_PAD_LEFT);
        $em = $this->getDoctrine()->getManager();
        $personRepo = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person');
        $user = $personRepo->findOneBy(array(
            'cpf' => $cpf
        ));
        if (! $user || ! $user->getNfgAccessToken()) {
            throw new NfgException('nfg.user.notfound');
        }
        $response = $this->redirect($this->generateUrl('lc_home'));
        try {
            $loginManager = $this->container->get('fos_user.security.login_manager');
            $firewallName = $this->container->getParameter('fos_user.firewall_name');
            $loginManager->loginUser($firewallName, $user, $response);
        } catch (AccountStatusException $ex) {
            // We simply do not authenticate users which do not pass the user
            // checker (not enabled, expired, etc.).
        }
        return $response;
    }

    /**
     * @Route("/bind", name="nfg_bind")
     */
    public function bindAction()
    {
        return $this->toNfg('nfg_url_auth', 'nfg_bindback');
    }

    /**
     * @Route("/bind/back", name="nfg_bindback")
     */
    public function bindBackAction(Request $request)
    {
        $person = $this->getUser();
        if (! $person) {
            return $this->redirect($this->generateUrl('lc_home'));
        }
        $result1 = $this->checkAccessToken($person->getVoterRegistration());
        $em = $this->getDoctrine()->getManager();
        $personRepo = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:Person');

        if ($person->getCpf()) {
            if ($person->getCpf() == $result1['CodCpf']) {
                // ok
            } else {
                $this->checkOtherPerson($result1, $em, $personRepo);

                $person->setCpf($result1['CodCpf']);
                $this->container->get('notifications.helper')->overwriteCpfNotification($otherPerson);
            }
        } else {
            $this->checkOtherPerson($result1, $em, $personRepo);
            $person->setCpf($result1['CodCpf']);
        }

        $nfgProfile = $em->getRepository('PROCERGSLoginCidadaoCoreBundle:NfgProfile')->findOneBy(array(
            'cpf' => $result1['CodCpf']
        ));
        if (! $nfgProfile) {
            $nfgProfile = new NfgProfile();
            $nfgProfile->setCpf($result1['CodCpf']);
        }
        $nfgProfile->setName($result1['NomeConsumidor']);
        $nfgProfile->setEmail($result1['EmailPrinc']);
        if (isset($result1['DtNasc'])) {
            $nfgProfile->setBirthdate(new \DateTime(str_replace('T', ' ', $result1['DtNasc'])));
            if (! $person->getBirthdate()) {
                $person->setBirthdate($nfgProfile->getBirthdate());
            }
        }
        if (isset($result1['NroFoneContato'])) {
            $nfgProfile->setMobile($result1['NroFoneContato']);
            if (! $person->getMobile()) {
                $person->setMobile($nfgProfile->getMobile());
            }
        }
        if ($result1['CodNivelAcesso']) {
            $nfgProfile->setAccessLvl($result1['CodNivelAcesso']);
        }
        if (isset($result1['CodSitTitulo'])) {
            $nfgProfile->setVoterRegistrationSit($result1['CodSitTitulo']);
            if (1 == $result1['CodSitTitulo']) {
                $nfgProfile->setVoterRegistration($person->getVoterRegistration());
            }
        }
        $em->persist($nfgProfile);

        $person->setNfgProfile($nfgProfile);
        $person->setNfgAccessToken($result1['paccessid']);
        if (! $person->getFirstName() || ! $person->getSurname()) {
            $nome = explode(' ', $result1['NomeConsumidor']);
            $person->setFirstName(array_shift($nome));
            $person->setSurname(implode(' ', $nome));
        }

        $this->container->get('fos_user.user_manager')->updateUser($person);
        return $this->redirect($this->generateUrl('lc_home'));
    }

    /**
     * @Route("/unbind", name="nfg_unbind")
     */
    public function unbindAction()
    {
        $person = $this->getUser();
        if ($person) {
            $person->setNfgAccessToken(null);
            $person->setNfgProfile(null);
            $this->container->get('fos_user.user_manager')->updateUser($person);
        }
        return $this->redirect($this->generateUrl('lc_home'));
    }
}
