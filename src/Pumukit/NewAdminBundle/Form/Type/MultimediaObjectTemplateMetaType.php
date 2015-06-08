<?php

namespace Pumukit\NewAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\NewAdminBundle\Form\Type\Other\Html5dateType;
use Symfony\Component\Translation\TranslatorInterface;

class MultimediaObjectTemplateMetaType extends AbstractType
{
    private $translator;
    private $locale;

    public function __construct(TranslatorInterface $translator, $locale='en')
    {
        $this->translator = $translator;
        $this->locale = $locale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('i18n_keyword', 'texti18n',
                  array(
                        'required' => false,
                        'label' => $this->translator->trans('Keyword', array(), null, $this->locale)))
            ->add('copyright', 'text',
                  array(
                        'required' => false,
                        'label' => $this->translator->trans('Copyright', array(), null, $this->locale)))
          ->add('broadcast', null, array('label' => $this->translator->trans('Broadcast', array(), null, $this->locale)))
            ->add('public_date', new Html5dateType(),
                  array(
                        'data_class' => 'DateTime',
                        'label' => $this->translator->trans('Public Date', array(), null, $this->locale)))
            ->add('record_date', new Html5dateType(),
                  array(
                        'data_class' => 'DateTime',
                        'label' => $this->translator->trans('Date of holding', array(), null, $this->locale)))
            ->add('i18n_description', 'textareai18n',
                  array(
                        'required' => false,
                        'label' => $this->translator->trans('Description', array(), null, $this->locale)))
            ->add('i18n_line2', 'textareai18n',
                  array(
                        'required' => false,
                        'label' => $this->translator->trans('Headline', array(), null, $this->locale)));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Pumukit\SchemaBundle\Document\MultimediaObject',
        ));
    }

    public function getName()
    {
        return 'pumukitnewadmin_mmtemplate_meta';
    }
}
