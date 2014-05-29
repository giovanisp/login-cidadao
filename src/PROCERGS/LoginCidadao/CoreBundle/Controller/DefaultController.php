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
use PROCERGS\LoginCidadao\CoreBundle\Entity\Uf;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;

class DefaultController extends Controller
{

    /**
     * @Route("/login/facebook", name="lc_link_facebook")
     */
    public function facebookLoginAction()
    {
        $shouldLogout = $this->getRequest()->get('logout');
        if (!is_null($shouldLogout)) {
            $this->get('session')->set('facebook.logout', true);
        }

        $api = $this->container->get('fos_facebook.api');
        $scope = implode(',',
                $this->container->getParameter('facebook_app_scope'));
        $callback = $this->container->get('router')->generate('_security_check_facebook',
                array(), true);
        $redirect_url = $api->getLoginUrl(array(
            'scope' => $scope,
            'redirect_uri' => $callback
        ));

        return new RedirectResponse($redirect_url);
    }

    /**
     * @Route("/lc_home_gateway", name="lc_home_gateway")
     * @Template()
     */
    public function gatewayAction()
    {
        return array(
            'home1' => $this->generateUrl('lc_home', array(), true)
        );
    }

    /**
     * @Route("/general", name="lc_general")
     * @Template()
     */
    public function generalAction()
    {
        return $this->render('PROCERGSLoginCidadaoCoreBundle:Info:terms.html.twig',
                        compact('user', 'apps'));
    }

    /**
     * @Route("/lc_consultaCep", name="lc_consultaCep")
     * @Template()
     */
    public function consultaCepAction(Request $request)
    {
        // $cep = new \PROCERGS\LoginCidadao\CoreBundle\Entity\Cep();
        $repoUf = $this->getDoctrine()->getEntityManager()->getRepository('PROCERGSLoginCidadaoCoreBundle:Uf');
        $q = $repoUf->createQueryBuilder('u')->orderBy('u.acronym');
        $p = $repoUf->findBy(array('acronym' => 'RS'));
        $form = $this->createFormBuilder()->add('adress', 'text',
                        array(
                    'required' => true,
                    'label' => 'form.adress',
                    'translation_domain' => 'FOSUserBundle'
                ))->add('adressnumber', 'text',
                        array(
                    'required' => false,
                    'label' => 'form.adressnumber',
                    'translation_domain' => 'FOSUserBundle'
                ))->add('city', 'text',
                        array(
                    'required' => true,
                    'label' => 'form.city',
                    'translation_domain' => 'FOSUserBundle'
                ))->add('uf', 'entity',
                        array(
                    'class' => 'PROCERGSLoginCidadaoCoreBundle:Uf',
                    'property' => 'name',
                    'required' => true,
                    'label' => 'form.uf',
                    'preferred_choices' => $p,
                    'query_builder' => $q,
                    'translation_domain' => 'FOSUserBundle'
                        )
                )->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $busca = $this->get('procergs_logincidadao.dne');
            $ceps = $busca->find(array(
                'logradouro' => $form->get('adress')->getData(),
                'localidade' => $form->get('city')->getData(),
                'numero' => $form->get('adressnumber')->getData(),
                'uf' => $form->get('uf')->getData()->getAcronym()
            ));
        } else {
            $ceps = array();
        }
        return array(
            'form' => $form->createView(),
            'ceps' => $ceps
        );
    }

    /**
     * @Route("/lc_consultaCep2", name="lc_consultaCep2")
     */
    public function consultaCep2Action(Request $request)
    {
        $busca = $this->get('procergs_logincidadao.dne');
        $ceps = $busca->findByCep($request->get('cep'));
        if ($ceps) {
            $result = array(
                'code' => 0,
                'msg' => null,
                'itens' => array(
                    $ceps
                )
            );
        } else {
            $result = array(
                'code' => 1,
                'msg' => 'not found'
            );
        }
        return new Response(json_encode($result), 200,
                array(
            'Content-Type' => 'application/json'
        ));
    }

    /**
     * @Route("/help", name="lc_help")
     * @Template()
     */
    public function helpAction(Request $request)
    {
        return $this->render('PROCERGSLoginCidadaoCoreBundle:Info:help.html.twig');
    }

    /**
     * @Route("/contact", name="lc_contact")
     * @Template()
     */
    public function contactAction(Request $request)
    {
        $form = $this->createForm(new ContactFormType());
        $form->handleRequest($request);
        $message = '';
        if ($form->isValid()) {
            $email = new SentEmail();
            $email->setType('contact-mail')->setSubject('Fale conosco - ' . $form->get('firstName')->getData())->setSender($form->get('email')->getData())->setReceiver($this->container->getParameter('mailer_receiver_mail'))->setMessage($form->get('message')->getData());
            if ($this->get('mailer')->send($email->getSwiftMail())) {
                $this->getDoctrine()->getEntityManager()->persist($email);
                $this->getDoctrine()->getEntityManager()->flush();
                $message = 'form.message.sucess';
            }
        }
        return $this->render('PROCERGSLoginCidadaoCoreBundle:Info:contact.html.twig',
                        array(
                    'form' => $form->createView(),
                    'messages' => $message
        ));
    }

    /**
     * @Route("/logout/if-not-remembered", name="lc_logout_not_remembered")
     * @Template()
     */
    public function logoutIfNotRememberedAction()
    {
        $result['logged_out'] = false;
        if ($this->getUser() instanceof UserInterface) {
            if ($this->getRequest()->cookies->has('REMEMBERME')) {
                $result = array('logged_out' => false);
            } else {
                $this->get("request")->getSession()->invalidate();
                $this->get("security.context")->setToken(null);
                $result['logged_out'] = true;
            }
        } else {
            $result['logged_out'] = true;
        }

        $response = new JsonResponse();
        $userAgent = $this->getRequest()->headers->get('User-Agent');
        if (preg_match('/(?i)msie [1-9]/', $userAgent)) {
            $response->headers->set('Content-Type', 'text/json');
        }
        return $response->setData($result);
    }

    /**
     * @Route("/jobs/polulate", name="lc_job_populate")
     * @Template()
     */
    public function populaAction()
    {
        ini_set('display_erros', 1);
        ini_set('error_reporting', E_ALL);
        ini_set('memory_limit','9999M');
        $userManager = $this->container->get('fos_user.user_manager');
        $em = $this->getDoctrine()->getEntityManager();
        foreach (range(1, 28) as $val) {
            if (($handle = fopen('c:\temp\file_'.$val.'.csv', "r")) !== FALSE) {
                $data = fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $user = $userManager->createUser();
                    $n = explode(' ', utf8_encode($data[3]));
                    $user->setFirstName(array_shift($n));
                    $user->setSurName(implode(' ', $n));
                    $user->setEmail($data[4]);
                    $user->setPassword($data[5]);
                    $user->setEnabled(true);
                    $userManager->updateUser($user);
                }
                fclose($handle);
                $em->flush();
                $em->clear();
            }
        }
        return new JsonResponse(array('ok'=> 'ok'));
    }

}
