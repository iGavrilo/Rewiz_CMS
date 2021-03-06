<?php
/**
 * Created by PhpStorm.
 * User: PC
 * Date: 16.08.2016
 * Time: 22:08
 */

namespace App\FrontModule\Presenters;

use App\Model\LeagueManager;
use App\Model\TournamentManager;
use App\Model\UserManager;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Database\UniqueConstraintViolationException;
use Nette\Utils\ArrayHash;
use Nette\Utils\Image;
use Tracy\Debugger;

class TeamPresenter extends BasePresenter
{

    /** @var LeagueManager */
    private $leagueManager;

    /** @var UserManager */
    private $userManager;

    /** @var  TournamentManager */
    public $tournamentManager;

    /**
     * TeamPresenter constructor.
     * @param LeagueManager $leagueManager
     * @param UserManager $userManager
     * @param TournamentManager $tournamentManager Automaticky injektovaná instace triedy XXX pre prácu s XXX
     */
    public function __construct(LeagueManager $leagueManager, UserManager $userManager, TournamentManager $tournamentManager)
    {
        $this->leagueManager = $leagueManager;
        $this->userManager = $userManager;
        $this->tournamentManager = $tournamentManager;
    }

    public function lastMatch($team_id){
        if (!$this->tournamentManager->lastMatch($team_id)){
            return FALSE;
        } else {
            return $this->tournamentManager->lastMatch($team_id);
        }
    }

    /**
     * @param int $team ID teamu
     * @param int $username ID uživateľa
     * @param string $action popis akcie
     */
    public function createLeagueLogs($team, $username, $action)
    {
        $this->leagueManager->createTeamLog($team, $username, $action);
        //TODO: Použiť array
    }

    /**
     * @param $id
     * @throws BadRequestException
     * Profil teamu
     */
    public function renderProfile($id)
    {

        $check = $this->leagueManager->getTeam($id);
        if (!$check) {
            throw new BadRequestException();
        }

        $this->template->team = $this->leagueManager->getTeam($id);
        $this->template->logs = $this->leagueManager->getTeamLogs($id);
        $this->template->member = $this->userManager->getTeamMember($id);
        $this->template->wins = $this->tournamentManager->getTeamWin($id);
        $this->template->played = $this->tournamentManager->getTeamRegistrationTournament($id);
        $this->template->matches = $this->tournamentManager->getTeamMatches($id)->fetchAll();
        $this->template->points = $this->leagueManager->getPoints($id);
        $this->template->awards = $this->leagueManager->getTeamAchviement($id);
    }

    /**
     * @param $id
     * Pripojenie do teamu
     */
    public function renderJoin($id)
    {
        /**
         * Ak je uživateľ prihlasení
         */
        if (!$this->user->isLoggedIn()) {
            $this->loginRedirect();
        }


        /**
         * Ak je v teame
         */
        if ($this->user->getIdentity()->team != NULL) {
            $this->flashMessage('Pred pridaním sa do nového teamu musíš opusiť starý', self::RED);
            $this->redirect('Team:profile', $this->user->getIdentity()->team);
        }

        $this->template->team = $this->leagueManager->getTeam($id);
    }

    /**
     * Registrácia teamu
     */
    public function renderRegistration()
    {
        /**
         * Ak je uživateľ prihlasení
         */
        if (!$this->user->isLoggedIn()) {
            $this->loginRedirect();
        }

        /**
         * Ak je v teame
         */
        if ($this->user->getIdentity()->team != NULL) {
            $this->flashMessage('Pred vytvorením nového teamu musíš opustiť ten aktuálny.', self::RED);
            $this->redirect('Team:profile', $this->user->getIdentity()->team);
        }
    }


    /**
     * @return Form
     */
    protected function createComponentJoinForm()
    {
        $form = new Form();

        $form->addText('password');
        $form->addSubmit('submit');

        $form->onSuccess[] = [$this, 'joinFormSucceeded'];

        return $form;

    }


    /**
     * @param Form $form
     * @param ArrayHash $values
     */
    public function joinFormSucceeded(Form $form, $values)
    {
        $id = $this->getParameter('id');
        $password = $this->leagueManager->getPassword($id);

        if ($password == $values->password) {
            $this->userManager->updateUser($this->user->getId(), array(
                'team' => $id,
            ));
            $this->leagueManager->createTeamLog($id, $this->user->getId(), 'Uživateľ vstúpil do teamu');
            $this->userManager->insertNotification($this->user->getId(), 'Vstúpil si do nového teamu.');
            $this->flashMessage('úspešne ste vstúpili do teamu');
            $this->redirect('Team:profile', $id);
        } else {
            $form->addError('Heslo nie je správne');
        }
    }

    /**
     * @return Form
     */
    protected function createComponentRegistrationForm()
    {
        $form = new Form();

        $form->addText('name')
            ->setAttribute('placeholder', 'Názov tímu')
            ->setRequired('Zadajte názov tímu')
            ->addRule(Form::MAX_LENGTH, 'Názov tímu môže obsahovať maximálne 10 znakov', 10);

        $form->addText('password')
            ->setAttribute('placeholder', 'Heslo')
            ->setRequired('Zadajte heslo tímu');

        $form->addText('tag')
            ->setAttribute('placeholder', 'Klan tag')
            ->setRequired('Zadajte klan tag')
            ->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaky', 3)
            ->addRule(Form::MAX_LENGTH, 'Klanový tag musí obsahovať %d znaky', 4);

        $form->addTextArea('about')
            ->setAttribute('placeholder', 'Informácie o tíme')
            ->setRequired('Zadajte informácie o tíme')
            ->addRule(Form::MAX_LENGTH, 'Informácie o tíme môžu obsahovať maximálne 150 znakov', 150);

        $form->addUpload('logo')
            ->setRequired('Vyberte logo')
            ->addRule(Form::IMAGE, 'Logo musí byť JPEG, PNG alebo GIF.');

        $form->addSubmit('submit');

        $form->onSuccess[] = [$this, 'registrationFormSucceeded'];

        return $form;
    }

    /**
     * @param Form $form
     * @param ArrayHash $values
     */
    public function registrationFormSucceeded(Form $form, $values)
    {
        $values->owner = $this->user->getId();

        /*var_dump($this->action);
        die();*/

        if ($values['logo']->isImage() and $values['logo']->isOk()) {
            $file = $values['logo']; //Prehodenie do $file
            $file_name = $file->getSanitizedName();
            $file->move($this->context->parameters['wwwDir'] . '/img/logo/' . $file_name);
            $image = Image::fromFile($this->context->parameters['wwwDir'] . '/img/logo/' . $file_name);
            $image->resize(129, 114, Image::EXACT);
            $image->sharpen();
            $image->save($this->context->parameters['wwwDir'] . '/img/logo/' . $file_name);
            $values['logo'] = $file_name;
            //TODO: random string na meno
            Debugger::dump($form);
            try {
                if ($this->action != 'edit') {
                    $query = $this->leagueManager->addTeam($values);
                    $data['team'] = $query->getPrimary();
                    $this->userManager->updateUser($this->user->getId(), $data);
                    $this->createLeagueLogs($data['team'], $this->user->getId(), 'Klan bol úspešne vytvorený.');
                } else {
                    $query = $this->leagueManager->editTeam($this->getParameter('id'), $values);
                    /*$data['team'] = $query->getPrimary();*/
                    $this->createLeagueLogs($this->getParameter('id'), $this->user->getId(), 'Klan bol úspešne editovaný.');
                }
            } catch (UniqueConstraintViolationException $e) {
                $form->addError('Team so zadaním tagom alebo názvom už existuje');
            }
            $this->reauthenticate($this->user->getId());
            if ($this->action != 'edit') {
                $this->userManager->insertNotification($this->user->getId(), 'Práve ste založili nový team');
                $this->flashMessage('Váš team ste úspešne založili');
            }else{
                $this->flashMessage('Váš team ste úspešne editovali');
            }
            $this->redirect('Homepage:default');
        }

    }

    public function renderEdit($id)
    {
        $query = $this->leagueManager->getTeam($id);

        if ($query->owner == $this->user->getId()) {
            $this['registrationForm']->setDefaults($query);
        } else {
            throw new BadRequestException();
        }
    }

    public function actionLeave()
    {
        $this->leagueManager->createTeamLog($this->user->getIdentity()->team, $this->user->getId(), 'Uživateľ vystúpil z teamu');
        $this->userManager->leaveTeam($this->user->getId());
        $this->userManager->insertNotification($this->user->getId(), 'Práve ste opustili váš team');
        $this->flashMessage('Úspešne si opustil tvoj team');
        $this->redirect('Homepage:default');
    }

    public function actionDeleteAch($team_id, $achviement_id)
    {
        $this->leagueManager->teamAchDel($team_id, $achviement_id);
        $this->flashMessage('Ocenenie vymazané');
        $this->redirect('Homepage:default');
    }
}