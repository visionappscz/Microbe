<?php

namespace WernerDweight\Microbe;

use WernerDweight\Dobee\Dobee;
use WernerDweight\MicrobeImageManager\Utils\ImageManagerUtility;
use WernerDweight\Microbe\framework\kernel\Kernel;
use WernerDweight\Microbe\framework\router\Router;
use WernerDweight\Microbe\framework\canonicalizer\Canonicalizer;
use WernerDweight\Microbe\framework\tokenizer\Tokenizer;
use WernerDweight\Microbe\framework\translator\Translator;
use WernerDweight\Microbe\framework\twig\AssetExtension;
use WernerDweight\Microbe\framework\twig\CanonicalizeExtension;
use WernerDweight\Microbe\framework\twig\PathExtension;
use WernerDweight\Microbe\framework\twig\RenderExtension;
use WernerDweight\Microbe\framework\twig\GeneralExtension;
use WernerDweight\Microbe\framework\twig\TextFunctionsExtension;
use WernerDweight\Microbe\framework\twig\TranslateExtension;
use WernerDweight\Microbe\framework\parenhancer\Parenhancer;
use WernerDweight\Microbe\framework\gatekeeper\Gatekeeper;
use WernerDweight\Microbe\framework\formbuilder\Twig\Extension\FormExtension;
use WernerDweight\Microbe\framework\flashmessenger\FlashMessenger;

class Microbe{

	public function __construct($configuration = [], $env = 'prod'){
		/// initialize parameters enhancer
		$parenhancer = new Parenhancer(str_replace('%env%',$env,$configuration['parameters']['path']));

		$configuration = $parenhancer->enhanceArray($configuration);

		/// initialize twig
		\Twig_Autoloader::register();
		$loader = new \Twig_Loader_Filesystem($configuration['twig']['pathToTemplates']);
		$twig = new \Twig_Environment($loader, [
			'cache' => $env !== 'prod' ? false : $configuration['twig']['pathToCache'],
		]);

		/// initialize dobee
		$dobee = null;
		if(isset($configuration['dobee']) && $configuration['dobee']['enable'] === true){
			$dobee = new Dobee(
				$parenhancer->enhance(
					file_get_contents($configuration['dobee']['pathToConfiguration'])
				),
				$configuration['dobee']['pathToEntities'],
				$configuration['dobee']['entityNamespace']
			);
		}

		/// initialize image manager
		$imageManager = null;
		if(isset($configuration['wd_image_manager']) && $configuration['wd_image_manager']['enable'] === true){
			$imageManager = new ImageManagerUtility([
				'versions' => $configuration['wd_image_manager']['versions'],
				'upload_root' => $configuration['wd_image_manager']['upload_root'],
				'upload_path' => $configuration['wd_image_manager']['upload_path'],
				'secret' => $configuration['wd_image_manager']['secret'],
			]);
		}

        /// initialize mailer
        $mailer = null;
        $mailerConfig = $configuration['swiftmailer'];
        if(isset($mailerConfig) && isset($mailerConfig['enable']) && $mailerConfig['enable'] === true){
            $transport = \Swift_MailTransport::newInstance();

            if (isset($mailerConfig['transport']) && $mailerConfig['transport'] === 'smtp') {
                $transport = \Swift_SmtpTransport::newInstance(
                    isset($mailerConfig['host']) ? $mailerConfig['host'] : null,
                    isset($mailerConfig['port']) ? $mailerConfig['port'] : null,
                    isset($mailerConfig['encryption']) ? $mailerConfig['encryption'] : null
                );

                if (isset($mailerConfig['auth_mode'])) {
                    $transport = $transport->setAuthMode($mailerConfig['auth_mode']);
                }

                if (isset($mailerConfig['username'])) {
                    $transport = $transport->setUsername($mailerConfig['username']);
                }

                if (isset($mailerConfig['password'])) {
                    $transport = $transport->setPassword($mailerConfig['password']);
                }
            }

            $mailer = \Swift_Mailer::newInstance($transport);
        }

		/// initialize gatekeeper
		$gatekeeper = Gatekeeper::getInstance();

		/// initialize flashmessenger
		$flashmessenger = FlashMessenger::getInstance();

		/// initialize router
		$router = new Router($configuration['router']['path'],$configuration['router']['firewall'],$gatekeeper->getRole());

		/// initialize translator
		$translator = null;
		if(isset($configuration['translator']) && $configuration['translator']['enable'] === true){
			$translator = new Translator($configuration['translator']['paths']);
			$twig->addExtension(new TranslateExtension($translator));
		}

		/// add twig extensions
		$twig->addExtension(new AssetExtension(
			$router,
			$configuration['environment'],
			$configuration['theme']
		));
		$twig->addExtension(new CanonicalizeExtension);
		$twig->addExtension(new PathExtension($router));
		$twig->addExtension(new RenderExtension($router));
		$twig->addExtension(new FormExtension($twig,$translator));
		$twig->addExtension(new GeneralExtension);
		$twig->addExtension(new TextFunctionsExtension);

		/// initialize canonicaler
		$canonicalizer = new Canonicalizer;

		/// initialize tokenizer
		$tokenizer = new Tokenizer;

		/// create an array of services to be passed to the application
		$services = [
			'twig' => $twig,
			'dobee' => $dobee,
			'imageManager' => $imageManager,
			'mailer' => $mailer,
			'router' => $router,
			'canonicalizer' => $canonicalizer,
			'tokenizer' => $tokenizer,
			'parenhancer' => $parenhancer,
			'gatekeeper' => $gatekeeper,
			'flashmessenger' => $flashmessenger,
			'translator' => $translator,
		];
		/// run app
		$kernel = new Kernel($services,$configuration);
	}

}

?>
