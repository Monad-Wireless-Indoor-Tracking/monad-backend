<?php

namespace App\Controller\Admin;

use App\Entity\News;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Doctrine\ORM\EntityManagerInterface;

/** Announcements shown in the app. */
class NewsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('News')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'content']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title');
        yield TextareaField::new('content')->hideOnIndex();
        yield AssociationField::new('createdBy', 'Author')
            ->formatValue(static fn ($value, $entity) => $entity->getCreatedBy()?->getEmail() ?? '—')
            ->hideOnForm();
        yield DateTimeField::new('createdAt', 'Created')->hideOnForm();
    }

    /** Authorship is taken from the session rather than asked for — one less field to get wrong. */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof News && $entityInstance->getCreatedBy() === null) {
            $entityInstance->setCreatedBy($this->getUser());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
