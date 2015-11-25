<?php
/**
 * @link https://github.com/old-town/workflow-zf2-view
 * @author  Malofeykin Andrey  <and-rey2@yandex.ru>
 */
namespace OldTown\Workflow\ZF2\View\Handler;

use OldTown\Workflow\ZF2\View\Handler\Context\ContextInterface;
use Traversable;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\Stdlib\ArrayUtils;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;
use Zend\View\Model\ModelInterface;

/**
 * Class AbstractHandler
 *
 * @package OldTown\Workflow\ZF2\View\Handler
 */
abstract class AbstractHandler implements HandlerInterface
{
    use EventManagerAwareTrait;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var MvcEvent
     */
    protected $mvcEvent;

    /**
     * @param array|Traversable $options
     *
     * @throws \Zend\Stdlib\Exception\InvalidArgumentException
     * @throws \OldTown\Workflow\ZF2\View\Handler\Exception\InvalidArgumentException
     */
    public function __construct($options = null)
    {
        $this->init($options);
    }

    /**
     * @param array $options
     *
     * @throws \Zend\Stdlib\Exception\InvalidArgumentException
     * @throws \OldTown\Workflow\ZF2\View\Handler\Exception\InvalidArgumentException
     */
    protected function init($options = null)
    {

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (null === $options) {
            $options = [];
        } elseif (!is_array($options)) {
            $errMsg = sprintf('%s  expects an array or Traversable config', __METHOD__);
            throw new Exception\InvalidArgumentException($errMsg);
        }

        if (array_key_exists('template', $options)) {
            $this->setTemplate($options['template']);
        }

        if (!array_key_exists('mvcEvent', $options)) {
            $errMsg = 'MvcEvent not found';
            throw new Exception\InvalidArgumentException($errMsg);
        }
        $this->setMvcEvent($options['mvcEvent']);
    }

    /**
     * Установка обработчиков по умолчанию
     */
    public function attachDefaultListeners()
    {
        $this->getEventManager()->attach(ContextInterface::EVENT_BOOTSTRAP, [$this, 'bootstrap'], -100);
        $this->getEventManager()->attach(ContextInterface::EVENT_TEMPLATE_RESOLVE, [$this, 'templateResolve'], -100);
        $this->getEventManager()->attach(ContextInterface::EVENT_DISPATCH, [$this, 'dispatch'], -100);
    }


    /**
     * Настройка хендлера
     *
     * @param ContextInterface $context
     */
    public function bootstrap(ContextInterface $context)
    {

    }

    /**
     * Установка нового шаблона
     *
     * @param ContextInterface $context
     */
    public function templateResolve(ContextInterface $context)
    {
        $template = $this->getTemplate();
        if ($template) {
            $this->getMvcEvent()->getViewModel()->setTemplate($template)->setTerminal(true);
        }

    }

    /**
     * Пред обработка данных
     *
     * @param ContextInterface $context
     *
     * @return mixed
     */
    public function dispatch(ContextInterface $context)
    {

    }

    /**
     * @param ContextInterface $context
     *
     * @return ModelInterface
     */
    public function run(ContextInterface $context)
    {
        $this->getEventManager()->trigger(ContextInterface::EVENT_BOOTSTRAP, $context);
        $this->getEventManager()->trigger(ContextInterface::EVENT_TEMPLATE_RESOLVE, $context);
        $resultDispatchHandler = $this->getEventManager()->trigger(ContextInterface::EVENT_DISPATCH, $context);
        $resultDispatch = $resultDispatchHandler->last();

        if (null === $resultDispatch) {
            $resultDispatch = [];
        }

        $mvcEvent = $this->getMvcEvent();
        $viewModel = $this->getMvcEvent()->getViewModel();
        $this->populateViewModel($resultDispatch, $viewModel, $mvcEvent);

        return $viewModel;
    }

    /**
     * Populate the view model returned by the AcceptableViewModelSelector from the result
     *
     * If the result is a ViewModel, we "re-cast" it by copying over all
     * values/settings/etc from the original.
     *
     * If the result is an array, we pass those values as the view model variables.
     *
     * @param  array|ViewModel $result
     * @param  ModelInterface $viewModel
     * @param  MvcEvent $e
     */
    protected function populateViewModel($result, ModelInterface $viewModel, MvcEvent $e)
    {
        if ($result instanceof ViewModel) {
            // "Re-cast" content-negotiation view models to the view model type
            // selected by the AcceptableViewModelSelector

            $viewModel->setVariables($result->getVariables());
            $viewModel->setTemplate($result->getTemplate());
            $viewModel->setOptions($result->getOptions());
            $viewModel->setCaptureTo($result->captureTo());
            $viewModel->setTerminal($result->terminate());
            $viewModel->setAppend($result->isAppend());
            if ($result->hasChildren()) {
                foreach ($result->getChildren() as $child) {
                    $viewModel->addChild($child);
                }
            }

            $e->setResult($viewModel);
            return;
        }

        // At this point, the result is an array; use it to populate the view
        // model variables
        $viewModel->setVariables($result);
        $e->setResult($viewModel);
    }


    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $template
     *
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return MvcEvent
     */
    public function getMvcEvent()
    {
        return $this->mvcEvent;
    }

    /**
     * @param MvcEvent $mvcEvent
     *
     * @return $this
     */
    public function setMvcEvent(MvcEvent $mvcEvent)
    {
        $this->mvcEvent = $mvcEvent;

        return $this;
    }

}
