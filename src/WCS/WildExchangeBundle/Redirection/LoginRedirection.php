<?php
namespace WCS\WildExchangeBundle\Redirection;

use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Router;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Doctrine\ORM\EntityManager;

class LoginRedirection implements AuthenticationSuccessHandlerInterface
{
    private $security;
    private $router;
    private $session;
    private $em;


    public function __construct(SecurityContext $security, Router $router, Session $session, EntityManager $em)
    {
        $this->security = $security;
        $this->router = $router;
        $this->session = $session;
        $this->em = $em;
    }
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $usr= $token->getUser();
        $usr->setDateConnexion(new \DateTime());
        $this->session->getFlashBag()->add('connexion', "Bonjour, ".$usr->getPseudo()." ! 😉 ");

        $this->em->flush();
        $response = new RedirectResponse($this->router->generate('homepage'));
        return $response;
    }
}