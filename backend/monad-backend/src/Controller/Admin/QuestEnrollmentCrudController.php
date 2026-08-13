<?php

namespace App\Controller\Admin;

use App\Entity\QuestEnrollment;
use App\Enum\QuestEnrollmentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Who is running which quest, and how far they got.
 *
 * Editable but not creatable: an enrollment is produced by a participant starting a quest in the
 * app, and one conjured here would have no step completions and no device behind it. Editing
 * exists for the real case — a session that ended badly and has to be marked abandoned so the
 * handset stops offering to resume it.
 */
class QuestEnrollmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QuestEnrollment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Enrollment')
            ->setEntityLabelInPlural('Enrollments')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('user')
            ->setFormTypeOption('choice_label', 'email')
            ->formatValue(static fn ($value, $entity) => $entity->getUser()?->getEmail() ?? '—');
        yield AssociationField::new('quest')
            ->setFormTypeOption('choice_label', 'name')
            ->formatValue(static fn ($value, $entity) => $entity->getQuest()?->getName() ?? '—');
        yield ChoiceField::new('status')
            ->setChoices(array_combine(
                array_map(static fn (QuestEnrollmentStatus $s) => $s->value, QuestEnrollmentStatus::cases()),
                QuestEnrollmentStatus::cases(),
            ))
            ->renderAsBadges([
                QuestEnrollmentStatus::IN_PROGRESS->value => 'info',
                QuestEnrollmentStatus::COMPLETED->value => 'success',
                QuestEnrollmentStatus::FAILED->value => 'danger',
                QuestEnrollmentStatus::ABANDONED->value => 'secondary',
            ]);
        yield TextField::new('dataPath', 'Data path')->setRequired(false)->hideOnIndex();
        yield DateTimeField::new('completedAt', 'Completed')->setRequired(false);
        yield DateTimeField::new('createdAt', 'Created')->hideOnForm();
        yield AssociationField::new('stepCompletions', 'Step completions')
            ->formatValue(static fn ($value, $entity) => $entity->getStepCompletions()->count() . ' completion(s)')
            ->onlyOnDetail();
    }
}
