<?php

namespace App\Controller\Admin;

use App\Entity\QrCode;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * The printed codes taped to doorframes.
 *
 * `value` is what a scan carries and it is UNIQUE — changing it after the code is on a wall
 * silently orphans every future scan of that printout, so it is editable but worth thinking
 * twice about. `position` is the human description of where it physically hangs, which is the
 * only way to find it again in a building.
 */
class QrCodeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QrCode::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('QR code')
            ->setEntityLabelInPlural('QR codes')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'value', 'position'])
            ->setHelp('edit', 'Changing `value` breaks every printout already hanging in the building.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name');
        yield TextField::new('value')->setHelp('Unique. This is the payload a scan carries.');
        yield TextareaField::new('position', 'Physical position')->hideOnIndex();
        yield AssociationField::new('createdBy', 'Created by')
            ->formatValue(static fn ($value, $entity) => $entity->getCreatedBy()?->getEmail() ?? '—')
            ->hideOnForm();
        yield DateTimeField::new('createdAt', 'Created')->hideOnForm();
    }

    /**
     * Authorship comes from the session, and it belongs here rather than in persistEntity:
     * createdBy is Assert\NotNull, and persistEntity runs after the validator has already
     * rejected the form. Setting it on the instance the form is about to bind to is the only
     * point early enough to count.
     */
    public function createEntity(string $entityFqcn): QrCode
    {
        $qrCode = new QrCode();
        $qrCode->setCreatedBy($this->getUser());

        return $qrCode;
    }
}
