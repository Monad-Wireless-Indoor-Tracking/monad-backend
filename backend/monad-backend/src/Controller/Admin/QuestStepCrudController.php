<?php

namespace App\Controller\Admin;

use App\Entity\QuestStep;
use App\Enum\QuestStepType;
use App\Form\JsonType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * The individual steps of a quest.
 *
 * `config` is step-type-specific and nested — an SSID for connect_to_ap, a zone for walk_to, a
 * duration for wait — so it is edited as raw JSON (see App\Form\JsonType) rather than through a
 * flattening list widget. Invalid JSON fails the form: a malformed config surfaces on a
 * participant's phone as a step that does nothing, which is the worst place to find out.
 */
class QuestStepCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QuestStep::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Quest step')
            ->setEntityLabelInPlural('Quest steps')
            ->setDefaultSort(['quest' => 'ASC', 'order' => 'ASC'])
            ->setSearchFields(['name']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('quest')
            ->setFormTypeOption('choice_label', 'name')
            ->formatValue(static fn ($value, $entity) => $entity->getQuest()?->getName() ?? '—');
        yield IntegerField::new('order', 'Order');
        yield TextField::new('name');
        yield ChoiceField::new('type')
            ->setChoices(array_combine(
                array_map(static fn (QuestStepType $t) => $t->value, QuestStepType::cases()),
                QuestStepType::cases(),
            ));
        yield Field::new('config')->formatValue(static fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->setFormType(JsonType::class)
            ->setHelp('Step-type-specific JSON. Validated as JSON, not against the step type.')
            ->hideOnIndex();
    }
}
