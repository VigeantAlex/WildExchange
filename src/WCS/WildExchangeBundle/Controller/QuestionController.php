<?php

namespace WCS\WildExchangeBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use WCS\WildExchangeBundle\Entity\Questions;
use WCS\WildExchangeBundle\Entity\Vote;
use WCS\WildExchangeBundle\Form\QuestionsType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class QuestionController extends Controller
{
    /**
     * @Route("/ajout", name="question_ajout")
     */
    public function ajoutAction(Request $request, $tag)
    {
        if (!$this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            $usr= $this->get('security.context')->getToken()->getUser();
            $this->addFlash('connexion', "Vous devez être connecté pour ajouter une question !");
            return $this->redirectToRoute('connectionpage');
        }
        // 1) build the form
        $question = new Questions();
        $form = $this->createForm(QuestionsType::class, $question);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $question->setDate(new \DateTime());
            $usr= $this->get('security.context')->getToken()->getUser();
            $question->setCreateur($usr);
            $em = $this->getDoctrine()->getManager();
            $tagobj = $em
                ->getRepository('WCSWildExchangeBundle:Tags')
                ->findByNom($tag);
            $question->addTag($tagobj[0]);
            $em->persist($question);
            $em->flush();
            $this->addFlash(
                'ajoutsuccess',
                'Votre question a bien été ajoutée !'
            );
            $this->checkBadges();

            return $this->redirectToRoute('questionpage', array('tag'=>$tag, 'page'=>    1));
        }

        return $this->render(
            'WCSWildExchangeBundle:Default:ajout.html.twig',
            array('form' => $form->createView())
        );
    }

    public function VoteAction(Request $request)
    {

        if (!$this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->render('WCSWildExchangeBundle:Default:vote.html.twig', array('question'=> $question));
        }
        $usr= $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();

        $this->checkBadges();
        if (isset($_POST['question_id'])){

            $question = $em
                ->getRepository('WCSWildExchangeBundle:Questions')
                ->find($_POST['question_id']);

            if (empty($question)){
                return $this->render('WCSWildExchangeBundle:Default:vote.html.twig', array('question'=> $question));
            }
            $votebool = $_POST['vote'] == 'plus' ? true : false;
            $existingvote = $em->getRepository('WCSWildExchangeBundle:Vote')
                ->findBy (['votant'=>$usr, 'question'=> $question], ['votant'=>'DESC'], 5, 0);
            if (empty($existingvote)){
                $vote = new Vote();
                $vote->setDate(new \DateTime());
                $vote->setVotant($usr);
                $vote->setValue($votebool);
                $vote->setQuestion($question);
                $em->persist($vote);
                $em->flush();
            }
            return $this->render('WCSWildExchangeBundle:Default:vote.html.twig', array('question'=> $question));
        }
        else if(isset($_POST['reponse_id'])){
            $reponse = $em
                ->getRepository('WCSWildExchangeBundle:Reponses')
                ->find($_POST['reponse_id']);

            if (empty($reponse)){
                return $this->render('WCSWildExchangeBundle:Default:vote.html.twig', array('question'=> $reponse));
            }

            $votebool = $_POST['vote'] == 'plus' ? true : false;

            $existingvote = $em->getRepository('WCSWildExchangeBundle:Vote')
                ->findBy (['votant'=>$usr, 'reponse'=> $reponse], ['votant'=>'DESC'], 5, 0);

            if (empty($existingvote)){
                $vote = new Vote();
                $vote->setDate(new \DateTime());
                $vote->setVotant($usr);
                $vote->setValue($votebool);
                $vote->setReponse($reponse);
                $em->persist($vote);
                $em->flush();
            }
            return $this->render('WCSWildExchangeBundle:Default:vote.html.twig', array('question'=> $reponse));

        }
    }

    public function rechercheAction($page){

        if (empty($_GET['q'])){
            return $this->redirectToRoute('homepage');
        }
        $querryarray = explode(' ', $_GET['q']);
        $em = $this->getDoctrine()->getManager();
        $querryquestion = array();
        $questions = $em
            ->getRepository('WCSWildExchangeBundle:Questions')
            ->findAll();

        foreach ($questions as $question) {
            foreach ($querryarray as $querry) {
                if (strpos($question->getTitre(), $querry) !== false || strpos($question->getContenu(), $querry) !== false) {
                    if (!in_array($question, $querryquestion)) {
                        array_push($querryquestion, $question);
                    }
                }
            }
        }
        if (empty($querryquestion)){
            $this->addFlash(
                'erreurrecherche',
                "Aucun résultat pour la recherche : '{$_GET['q']}' !"
            );

            $referer = $this->getRequest()->headers->get('referer');

            return $this->redirect($referer);
        }

        $pagequerry = $page*5-5;
        $allquestion = $querryquestion;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : null;
        switch ($sort) {
            case 'vote':
                $allquestion = $this->sortbyVote($allquestion);
                break;
            default:
                break;
        }
        $listquestion = array_slice($allquestion, $pagequerry, 5);
        $maxpage = round(count($allquestion)/5, 0, PHP_ROUND_HALF_UP);

        return $this->render('WCSWildExchangeBundle:Default:questions.html.twig', array('tag' => '', 'questions'=> $listquestion, 'maxpage' => $maxpage, 'actual'=> $page, 'q'=> str_replace(" ", "+", $_GET['q']), 'sort' => $sort));

    }

    public function sortbyVote($list){

        usort($list, function($a, $b) {
            return count($a->getVotes()->getValues()) - count($b->getVotes()->getValues());
        });
        $list = array_reverse($list);
        return $list;


    }

    public function checkBadges(){
        $usr= $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();

        $allbadge = $em
            ->getRepository('WCSWildExchangeBundle:Badge')
            ->findAll();
        foreach ($allbadge as $badge){

            if($badge->getMinquestion()){
                if (count($usr->getQuestions()) >= $badge->getMinquestion() )
                {
                    if (!in_array($badge, $usr->getBadges()->getValues())) {
                        $badge->addUtilisateur($usr);
                        $usr->addBadge($badge);
                        $em->persist($badge);
                        $em->persist($usr);
                    }
                }
            }
            else if ($badge->getMinreponse()){
                if (count($usr->getReponses()) >= $badge->getMinreponse() )
                {
                    if (!in_array($badge, $usr->getBadges()->getValues())) {
                        $badge->addUtilisateur($usr);
                        $usr->addBadge($badge);
                        $em->persist($badge);
                        $em->persist($usr);
                    }
                }
            }
            else if ($badge->getMinvote()){
                if (count($usr->getVotes()) >= $badge->getMinvote() )
                {
                    if (!in_array($badge, $usr->getBadges()->getValues())) {
                        $badge->addUtilisateur($usr);
                        $usr->addBadge($badge);
                        $em->persist($badge);
                        $em->persist($usr);
                    }

                }
            }
        }

        $em->flush();
    }
    public function deleteAction($id){
        if (!$this->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return $this->redirectToRoute('homepage');
        }
        $usr= $this->get('security.context')->getToken()->getUser();
        $em = $this->getDoctrine()->getManager();
        $question = $em
            ->getRepository('WCSWildExchangeBundle:Questions')
            ->find($id);

        if($question->getCreateur() == $usr || $usr->getRang()->getId() >= 2){
            $tag = $question->getTags()[0]->getNom();
            $em->remove($question);
            $em->flush();
            $this->addFlash(
                'deletesuccess',
                'La question a bien était supprimer !'
            );
            return $this->redirectToRoute('questionpage', array('tag'=>$tag, 'page'=>1));
        }
        else{
            $this->addFlash(
                'faildelete',
                'Vous ne pouvez pas supprimer cette question !'
            );
            $referer = $this->getRequest()->headers->get('referer');

            return $this->redirect($referer);
        }

        $referer = $this->getRequest()->headers->get('referer');

        return $this->redirect($referer);

    }
}