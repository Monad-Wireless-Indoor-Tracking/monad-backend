<?php

namespace App\Controller\Admin;

use App\Entity\Quest;
use App\Form\JsonType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Quests — the schedule a participant's phone walks through.
 *
 * `availableFrom` / `availableTo` decide what /api/quests returns, so an edit here changes what
 * every handset sees at its next poll. That is the point of the screen, and the reason the dates
 * are on the index rather than buried in a form.
 */
class QuestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Quest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Quest')
            ->setEntityLabelInPlural('Quests')
            ->setDefaultSort(['availableFrom' => 'DESC'])
            ->setSearchFields(['name', 'description']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // IP-149 — funnel, durations, skip reasons and failing steps for this quest.
        $analytics = Action::new('analytics', 'Analytics', 'fa fa-chart-simple')
            ->linkToRoute('admin_quest_analytics', static fn (Quest $q) => ['id' => $q->getId()?->toRfc4122()]);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $analytics)
            ->add(Crud::PAGE_DETAIL, $analytics);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name');
        yield TextareaField::new('description')->hideOnIndex();
        yield DateTimeField::new('availableFrom', 'From');
        yield DateTimeField::new('availableTo', 'To')->setRequired(false);
        yield NumberField::new('points')->setNumDecimals(1);
        yield IntegerField::new('estimatedDuration', 'Est. minutes')->setRequired(false)->hideOnIndex();
        // Matched against the capability tokens the phone reports (DeviceCapabilities): a quest
        // asking for a capability the handset withholds is one the participant never sees.
        yield Field::new('requiredCapabilities', 'Required capabilities')->formatValue(static fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->setFormType(JsonType::class)
            ->setHelp('JSON array, e.g. ["wifi_associate","ble_witness"]. Matched against what the phone reports.')
            ->hideOnIndex();
        yield TextField::new('featuredImage')->setRequired(false)->hideOnIndex();
        // IP-128 — which physical nodes offer this quest. EMPTY MEANS EVERY NODE,
        // which is what every quest written before IP-128 means; treating empty as
        // "nowhere" would silently unpublish the whole existing catalogue.
        yield AssociationField::new('armedDevices', 'Armed at devices')
            ->setRequired(false)
            ->setHelp('Leave empty to offer this quest at EVERY node. Pick nodes to restrict it.')
            ->hideOnIndex();
        // IP-128 — replay policy. NULL/blank means UNLIMITED, which is the behaviour
        // every quest has today (nothing in this backend has ever blocked a replay).
        // There is deliberately no system-wide default: a measurement quest wants
        // none, so a pre-registered session can run the same nodes repeatedly in one
        // afternoon; an evergreen "collect the fleet" quest wants a cooldown.
        yield Field::new('recurrence', 'Replay policy')
            ->formatValue(static fn ($value) => null === $value
                ? 'unlimited'
                : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->setFormType(JsonType::class)
            ->setRequired(false)
            ->setHelp(
                'Blank = unlimited replays (the default, and what every existing quest does). '
                .'To add a cooldown: {"scope":"per_device","cooldown_seconds":21600} — per_device '
                .'makes it independently replayable at each node, per_quest counts it anywhere. '
                .'A malformed policy degrades to unlimited rather than breaking the catalogue.'
            )
            ->hideOnIndex();
        yield AssociationField::new('steps')
            ->formatValue(static fn ($value, $entity) => $entity->getSteps()->count() . ' step(s)')
            ->onlyOnDetail();
        yield AssociationField::new('createdBy', 'Created by')
            ->setRequired(false)
            ->setFormTypeOption('choice_label', 'email')
            ->formatValue(static fn ($value, $entity) => $entity->getCreatedBy()?->getEmail() ?? '—')
            ->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Created')->hideOnForm()->onlyOnDetail();
    }
}
