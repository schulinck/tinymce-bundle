<?php
namespace Stfalcon\Bundle\TinymceBundle\Twig\Extension;

use Stfalcon\Bundle\TinymceBundle\Helper\LocaleHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Twig Extension for TinyMce support.
 *
 * @author naydav <web@naydav.com>
 */
class StfalconTinymceExtension extends \Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Asset Base Url
     * Used to over ride the asset base url (to not use CDN for instance)
     *
     * @var String
     */
    protected $baseUrl;

    /**
     * Initialize tinymce helper
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Gets a service.
     *
     * @param string $id The service identifier
     *
     * @return object The associated service
     */
    public function getService($id)
    {
        return $this->container->get($id);
    }

    /**
     * Get parameters from the service container
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('tinymce_init', array($this, 'tinymceInit'), array('is_safe' => array('html')))
        );
    }

    /**
     * TinyMce initializations
     *
     * @param array $options
     * @return string
     */
    public function tinymceInit($options = array(), $replace = false)
    {
        $config = $this->getParameter('stfalcon_tinymce.config');
        if ($replace) {
            $config = array_replace_recursive($config, $options);
        } else {
            $config = array_merge_recursive($config, $options);
        }

        $tinyMCE_config = $config['tinymce_config'];
        $tinyMCE_config['use_callback_tinymce_init'] = $config['use_callback_tinymce_init'];

        $this->baseUrl = (!isset($tinyMCE_config['base_url']) ? null : $tinyMCE_config['base_url']);

        // Get path to tinymce script for the jQuery version of the editor
        if ($config['tinymce_jquery']) {
            $config['jquery_script_url'] = $this->getUrl(
                $this->baseUrl . 'bundles/stfalcontinymce/vendor/tinymce/tinymce.jquery.min.js'
            );
        }

        // Get local button's image
        if(isset($tinyMCE_config['tinymce_buttons']) && is_array($tinyMCE_config['tinymce_buttons'])){
            foreach ($tinyMCE_config['tinymce_buttons'] as &$customButton) {
                if ($customButton['image']) {
                    $customButton['image'] = $this->getAssetsUrl($customButton['image']);
                } else {
                    unset($customButton['image']);
                }

                if ($customButton['icon']) {
                    $customButton['icon'] = $this->getAssetsUrl($customButton['icon']);
                } else {
                    unset($customButton['icon']);
                }
            }
        }
        
        // Update URL to external plugins
        if(isset($tinyMCE_config['external_plugins']) && is_array($tinyMCE_config['external_plugins'])){
            foreach ($tinyMCE_config['external_plugins'] as &$extPlugin) {
                $extPlugin['url'] = $this->getAssetsUrl($extPlugin['url']);
            }
        }

        // If the language is not set in the config...
        if (!isset($tinyMCE_config['language']) || empty($tinyMCE_config['language'])) {
            // get it from the request
            if ($this->container->has('request_stack')) {
                $request = $this->getService('request_stack')->getCurrentRequest();
            } else {
                $request = $this->getService('request');
            }
            if ($request) {
                $tinyMCE_config['language'] = $request->getLocale();
            }
        }

        $tinyMCE_config['language'] = LocaleHelper::getLanguage($tinyMCE_config['language']);

	    $langDirectory = __DIR__ . '/../../Resources/public/vendor/tinymce-langs/';

        // A language code coming from the locale may not match an existing language file
        if (!file_exists($langDirectory . $tinyMCE_config['language'] . '.js')) {
            unset($tinyMCE_config['language']);
        }

        if (isset($tinyMCE_config['language']) && $tinyMCE_config['language']) {
            $languageUrl = $this->getUrl(
                $this->baseUrl.'bundles/stfalcontinymce/vendor/tinymce-langs/'.$tinyMCE_config['language'].'.js'
            );
            // TinyMCE does not allow to set different languages to each instance
            foreach ($tinyMCE_config['theme'] as $themeName => $themeOptions) {
                $tinyMCE_config['theme'][$themeName]['language'] = $tinyMCE_config['language'];
                $tinyMCE_config['theme'][$themeName]['language_url'] = $languageUrl;
            }
            $tinyMCE_config['language_url'] = $languageUrl;
        }

        if (isset($tinyMCE_config['theme']) && $tinyMCE_config['theme'])
        {
            // Parse the content_css of each theme so we can use 'asset[path/to/asset]' in there
            foreach ($tinyMCE_config['theme'] as $themeName => $themeOptions) {
                if(isset($themeOptions['content_css']))
                {
                    // As there may be multiple CSS Files specified we need to parse each of them individually
                    $cssFiles = explode(',', $themeOptions['content_css']);

                    foreach($cssFiles as $idx => $file)
                    {
                        $cssFiles[$idx] = $this->getAssetsUrl(trim($file)); // we trim to be sure we get the file without spaces.
                    }

                    // After parsing we add them together again.
                    $tinyMCE_config['theme'][$themeName]['content_css'] = implode(',', $cssFiles);
                }
            }
        }

        return $this->getService('templating')->render('StfalconTinymceBundle:Script:init.html.twig', array(
            'tinymce_config' => preg_replace(
                '/"(file_browser_callback|file_picker_callback)":"([^"]+)"\s*/', '$1:$2',
                json_encode($tinyMCE_config)
            ),
            'include_jquery' => $config['include_jquery'],
            'tinymce_jquery' => $config['tinymce_jquery'],
            'base_url'       => $this->baseUrl
        ));
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'stfalcon_tinymce';
    }

    /**
     * Get url from config string
     *
     * @param string $inputUrl
     *
     * @return string
     */
    protected function getAssetsUrl($inputUrl)
    {
        $url = preg_replace('/^asset\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $url) {
            return $this->getUrl($this->baseUrl . $url);
        }

        return $inputUrl;
    }

    protected function getUrl($url)
    {
        if ($this->container->has('assets.packages')) {
            return $this->container->get('assets.packages')->getUrl($url);
        }
        if ($this->container->has('templating.helper.assets')) {
            return $this->container->get('templating.helper.assets')->getUrl($url);
        }

        return $url;
    }

    /**
     * Expands a short locale to a long one
     *
     * @param string $locale
     * @return string
     */
    protected function expandLocale($locale)
    {
	$conversion = array(
	    'bn' => 'bn_BD',
	    'en' => 'en_GB',
	    'he' => 'he_IL',
	    'ka' => 'ka_GE',
	    'km' => 'km_KH',
	    'ko' => 'ko_KR',
	    'nb' => 'nb_NO',
	    'si' => 'si_LK',
	    'sl' => 'sl_SI',
	    'sv' => 'sv_SE',
	    'zh' => 'zh_CN',
	);
	if (array_key_exists($locale, $conversion)) {
	    return $conversion[$locale];
	}

	return $locale;
    }
}
