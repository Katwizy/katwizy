<?php

namespace Potherca\Katwizy;

use \Directory;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouteCollectionBuilder;

/**
 * @FIXME: Various hard-coded values for directories need to be read from a config file!
 */
class AppKernel extends Kernel
{
    const DEBUG = 'debug';
    const DEVELOPMENT = 'dev';
    const ENVIRONMENT = 'environment';
    const PRODUCTION = 'prod';

    private $projectPath;

    use MicroKernelTrait;

    final public function __construct(Directory $projectDirectory, array $options = [])
    {
        $this->projectPath = $projectDirectory->path;

        $options = array_merge(
            [self::ENVIRONMENT => self::PRODUCTION, self::DEBUG => false],
            $options
        );
        parent::__construct($options[self::ENVIRONMENT], $options[self::DEBUG]);
    }

    final public function registerBundles()
    {
        //@FIXME: Also load (other) packages from the project configuration
        $bundles = [];

        $productionBundles = [
            \Symfony\Bundle\FrameworkBundle\FrameworkBundle::class,
            \Symfony\Bundle\SecurityBundle\SecurityBundle::class,
            \Symfony\Bundle\TwigBundle\TwigBundle::class,
            \Symfony\Bundle\MonologBundle\MonologBundle::class,
            \Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle::class,
            \Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class,
            \Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class,
            \AppBundle\AppBundle::class,
        ];

        $developmentBundles = [
            \Symfony\Bundle\DebugBundle\DebugBundle::class,
            \Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class,
            \Sensio\Bundle\DistributionBundle\SensioDistributionBundle::class,
            // \Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle::class,
        ];

        $loadBundles = function ($bundle) use (&$bundles) {
            $bundles[] = new $bundle;
        };

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            array_walk($developmentBundles, $loadBundles);
        }

        array_walk($productionBundles, $loadBundles);

        return $bundles;
    }

    final public function getVarDir()
    {
        return $this->projectPath.'/var/'.$this->environment;
    }

    final public function getLogDir()
    {
        return $this->getVarDir().'/logs';
    }

    final public function getCacheDir()
    {
        return $this->getVarDir().'/cache';
    }

    public function getRootDir()
    {
        return $this->projectPath.'/src/';
    }

    final public function configureContainer(ContainerBuilder $containerBuilder, LoaderInterface $loader)
    {
        $standardConfigDirectory = $this->projectPath.'/vendor/symfony/framework-standard-edition/app/config';
        $projectConfigDirectory = $this->projectPath.'/config';

        $defaultConfig = [
            'templating' => [
                'engines' => ['twig'],
            ],
            'profiler' => [
                "only_exceptions" =>  false,
            ],
            'session' => [
                'handler_id' => 'session.handler.native_file',
                'save_path'  => $this->getVarDir() . '/sessions',
            ]
        ];

        /*/ Add require parameter if not present in project /*/
        if (is_readable($projectConfigDirectory.'/config.yml') === false
            && is_readable($projectConfigDirectory.'/parameters.yml') === false
        ) {
            $defaultConfig['secret'] = 'S0ME_SECR3T';// @FIXME: Make the secret a required environment variable!
        }

        // PHP equivalent of `config.yml`
        $containerBuilder->loadFromExtension('framework', $defaultConfig);

        /*/ Add prameters if present /*/
        if (is_readable($projectConfigDirectory.'/parameters.yml')) {
            $loader->load($projectConfigDirectory . '/parameters.yml');;
        }

        $loader->load($standardConfigDirectory . '/security.yml');

        $loader->load($standardConfigDirectory . '/services.yml');

        // (?) $loader->load($standardConfigDirectory.'/config.yml');

        /*/ Add project configuration if present /*/
        if (is_readable($projectConfigDirectory.'/config.yml')) {
            $loader->load($projectConfigDirectory.'/config.yml');
        }

        /*/ configure WebProfilerBundle only if the bundle is enabled /*/
        if (isset($this->bundles['WebProfilerBundle'])) {
            $containerBuilder->loadFromExtension('web_profiler', array(
                'toolbar' => true,
                'intercept_redirects' => false,
            ));
        }
    }

    final public function configureRoutes(RouteCollectionBuilder $routes)
    {
        // 'kernel' is the name of a service that points to this class
        // optional 3rd argument is the route name
        //$routes->add('/', 'kernel:homeAction', 'Homepage');

        // import the WebProfilerRoutes if the bundle is enabled
        if (isset($this->bundles['WebProfilerBundle'])) {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml', '/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml', '/_profiler');
        }

        /*/ load routes from source annotations /*/
        if (is_dir($this->projectPath.'/src/')) {
            $routes->import($this->projectPath.'/src/', '/', 'annotation');
        }

        /*/ load routes from web annotations /*/
        if (is_dir($this->projectPath.'/web/')) {
            $routes->import($this->projectPath.'/web/', '/', 'annotation');
        }

        /*/ load routes from configurayion file /*/
        if (is_readable($this->projectPath.'/config/routing.yml')) {
            $routes->import($this->projectPath.'/config/routing.yml');
        }
    }
}

/*EOF*/