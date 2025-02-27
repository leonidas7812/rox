<?php

namespace App\EventListener;

use App\Doctrine\MemberStatusType;
use App\Entity\Member;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Listens for interactive login to set the member status to active in case the login was done from an OutOfRemind or
 * other browsable state.
 */
class AuthListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function onAuthenticationSuccess(InteractiveLoginEvent $event)
    {
        /** @var Member $member */
        $member = $event->getAuthenticationToken()->getUser();
        if (MemberStatusType::OUT_OF_REMIND === $member->getStatus()) {
            $member->setStatus(MemberStatusType::ACTIVE);
            $this->entityManager->persist($member);
            $this->entityManager->flush();
        }
    }
}
