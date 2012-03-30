<?php

require_once 'lib/php/Zend/Exception.php';
require_once 'lib/php/Zend/Console/Getopt.php';
require_once 'lib/php/Zend/Console/Getopt/Exception.php';

class UNL_WDNTemplates_Compressor
{
    const JS_COMPILER_CLOSURE  = 'closure';
    const JS_COMPILER_UGLIFYJS = 'uglify-js';

    /**
     * The header appended to compressed files
     *
     * @var string
     */
    protected $_wdnHeader = '/**
 * This file is part of the UNL WDN templates.
 * @see http://wdn.unl.edu/
 * $Id$
 */

';

    protected $_debugHeader = '/*
*
* !DO NOT EDIT THIS FILE, IT IS BUILT WITH THE PROJECT BUILD PROCESS
*
* ---------------------------
* run `make debug` to rebuild this file
* ---------------------------
*/

';

    /**
     * The relative location to the root of template directory
     *
     * @var string
     */
    protected $_srcDir = '../';

    /**
     * The location of the template files
     *
     * @var string
     */
    protected $_templateDir = 'wdn/templates_3.1/';

    /**
     * The URI to the loaded javascript template files
     *
     * @var unknown_type
     */
    protected $_templatePath = '/';

    /**
     * JavaScript files for the templates orginized into arrays for
     * mobile and desktop. Names ending in .min will not be further compressed
     *
     * @var array
     */
    protected $_jsFiles = array(
        'all' => array(
            'wdn',
            'modernizr-wdn',
            'analytics',
            'idm',
            'navigation',
            'search',
            'unlalert',
        ),
        '320' => array(

        ),
        '768' => array(
            // Not further compressed, prepended to below
            'jquery.min',
            'plugins/hoverIntent/jQuery.hoverIntent.min',
            // Compressed and merged
            'wdn_ajax',
            'global_functions',
            'feedback',
            'socialmediashare',
            'toolbar',
            'tabs',
        )
    );

    /**
     * The CSS/LESS files that need to be compiled and compressed.
     *
     * The format of this array should be such that the keys are the
     * filenames and the values are file options. If as key is not given,
     * the value is used as the filename and the file options are assumed
     * empty.
     *
     * File options may be an array with any of the following keys:
     * <code>'ignore' => (boolean)</code> Skips this file in the compression
     * <code>'noless' => (boolean)</code> Skips this file in the less compilation
     *
     * @var array
     */
    protected $_cssFiles = array(
        'foundation/reset' => array('noless' => true),
        'foundation/global',
        'fonts/fonts' => array('noless' => true),
        'wrapper/wrapper',
        'header/header',
        'header/search',
        'header/idm',
        'header/tools',
        'header/tools_content' => array('ignore' => true),
        'header/tooltabs' => array('ignore' => true),
        'header/colorbox' => array('ignore' => true),
        'header/unlalert' => array('ignore' => true),
        'navigation/breadcrumbs',
        'navigation/navigation',
        'content/maincontent',
        'content/grid',
        'content/headers',
        'content/images',
        'content/mime',
        'content/tabs',
        'content/pagination' => array('ignore' => true),
        'content/zenbox',
        'content/zentable',
        'footer/footer',
        'footer/feedback',
        'footer/share',
        'script' => array('ignore' => true),
        'content/css3_selector_failover' => array('ignore' => true),
        'variations/ie' => array('ignore' => true),
        'variations/touch' => array('ignore' => true),
    );

    protected $_supportedMediaWidths = array(
        320,
        480,
        600,
        768,
        960,
        1040
    );

    protected $_buildTargets = array(
        'js' => array(
            'in' => 'scripts',
            'out' => 'scripts/compressed',
            'files' => array(
                'all.js',
                '320.js',
                '768.js',
            )
        ),
        'css' => array(
            'in' => 'css',
            'out' => 'css/compressed',
            'files' => array(
                'base.css',
                'combined_widths.css',
            )
        ),
        'css_debug' => array(
            'in' => '', // !SPECIAL: relative to out
            'out' => 'css',
            'files' => array(
                'debug.css'
            )
        ),
        'less' => array(
            'in' => 'less',
            'out' => 'css',
        )
    );

    /**
     * The compiler type to use for javascript compression
     *
     * @var string Expects one of the JS_COMPILER_* constants
     */
    protected $_compiler = self::JS_COMPILER_CLOSURE;

    /**
     * Should the build targets be checked for last build time?
     *
     * @var boolean
     */
    protected $_checkTime = true;

    /**
     * Should the builds be verbose?
     *
     * @var boolean
     */
    protected $_verbosity = false;

    /**
     * Gets the directory location of template files
     *
     * @return string
     */
    public function getTemplateDir()
    {
        return $this->_templateDir;
    }

    /**
     * Sets the directory location of template files
     *
     * @param string $dir
     * @return UNL_WDNTemplates_Compressor
     */
    public function setTemplateDir($dir)
    {
        $this->_templateDir = rtrim($dir, '/') . '/';

        return $this;
    }

    /**
     * Gets the loaded javascript template path
     *
     * @return string
     */
    public function getTemplatePath()
    {
        return $this->_templatePath;
    }

    /**
     * Sets the loaded javascript template path
     *
     * @param string $path
     * @return UNL_WDNTemplates_Compressor
     */
    public function setTemplatePath($path)
    {
        $this->_templatePath = rtrim($path, '/') . '/';

        return $this;
    }

    /**
     * Get the compiler type to use for javascript compression
     *
     * @return string
     */
    public function getCompiler()
    {
        return $this->_compiler;
    }

    /**
     * Sets the flag to force building targets
     *
     * @param boolean $force [OPTIONAL] Should the build be forced
     * @return UNL_WDNTemplates_Compressor
     */
    public function forceBuild($force = true)
    {
        $this->_checkTime = !$force;

        return $this;
    }

    /**
     * Sets the flag to enforce build verbosity
     *
     * @param boolean $verbose [OPTIONAL] Should the build be verbose
     * @return UNL_WDNTemplates_Compressor
     */
    public function verbose($verbose = true)
    {
        $this->_verbosity = (bool)$verbose;

        return $this;
    }

    /**
     * Sets the compiler type to use for javascript compression
     *
     * @param string $compiler
     * @return UNL_WDNTemplates_Compressor
     */
    public function setCompiler($compiler)
    {
        switch ($compiler) {
            case self::JS_COMPILER_CLOSURE:
            case self::JS_COMPILER_UGLIFYJS:
                $this->_compiler = $compiler;
                break;
            default:
                $this->_compiler = self::JS_COMPILER_CLOSURE;
                break;
        }

        return $this;
    }

    /**
     * Echos (to std_out) a message if configured for verbosity
     *
     * @param array|string $msg
     * @return UNL_WDNTemplates_Compressor
     */
    protected function _announce($msg)
    {
        if ($this->_verbosity) {
            if (is_array($msg)) {
                $msg = implode(PHP_EOL, $msg);
            }

            echo $msg . PHP_EOL;
        }

        return $this;
    }

    /**
     * Returns the real path to the given template resource
     *
     * @param string $path
     * @return string
     */
    protected function _getSrcTemplatePath($path)
    {
        return realpath(dirname(__FILE__) . "/{$this->_srcDir}{$this->_templateDir}{$path}");
    }

    /**
     * If configured to check time, will return if any of the given prereqs are
     * newer than the given target file. Otherwise, always returns true.
     *
     * @param string $targetFile
     * @param string $inDir
     * @param array $prereqs An array of prerequisite files relative to $inDir
     * @param string $suffix The file extension to append to each $prereqs
     * @param string $target
     * @param string $customMsg [OPTIONAL] The message to echo if $targetFile is the newest
     * @return boolean
     */
    protected function _checkMtime($targetFile, $inDir, $prereqs, $suffix, $target, $customMsg = false)
    {
        if ($this->_checkTime) {
            $targetAge = file_exists($targetFile) ? filemtime($targetFile) : false;
            $prereqAge = false;

            foreach ($prereqs as $file) {
                $tempAge = filemtime("{$inDir}/{$file}.{$suffix}");

                if ($tempAge > $prereqAge) {
                    $prereqAge = $tempAge;
                }
            }

            if ($targetAge && $prereqAge < $targetAge) {
                // All prerequisites are older than the latest build, we're done
                if (!$customMsg) {
                    $customMsg = "[NOTICE] Nothing to be done for {$target} target";
                }

                $this->_announce($customMsg);
                return false;
            }
        }

        return true;
    }

    /**
     * Compiles the template javascript and minifies it.
     *
     * @return UNL_WDNTemplates_Compressor
     */
    public function buildJs()
    {

        $inDir = $this->_getSrcTemplatePath($this->_buildTargets['js']['in']);
        $outDir = $this->_getSrcTemplatePath($this->_buildTargets['js']['out']);
        $outFiles = $this->_buildTargets['js']['files'];

        $this->_announce('Building javascript');

        $i = 0;
        foreach ($this->_jsFiles as $sub => $files) {
            $outro = '';
            if ($sub == 'all') {
                $outro = PHP_EOL . 'WDN.initializeTemplate();' . PHP_EOL;
            }

            if (!$this->_buildJsTarget($inDir, $outDir, $outFiles[$i++], $files, $outro)) {
                return $this;
            }
        }

        $this->_announce('javascript build complete');

        return $this;
    }

    /**
     * Builds a given javascript sub-target
     *
     * @param string $inDir
     * @param string $outDir
     * @param string $outFile
     * @param array $files
     * @param string $outro [OPTIONAL]
     * @return boolean
     */
    protected function _buildJsTarget($inDir, $outDir, $outFile, $files, $outro = '')
    {
        // Check if we need a new compression
        if ($this->_checkMtime("{$outDir}/{$outFile}", $inDir, $files, 'js', 'javascript')) {
            $min = '';
            $all = '';

            foreach ($files as $file) {
                if (substr($file, -4) == '.min') {
                    $set = 'min';
                } else {
                    $set = 'all';
                }

                $filename = "{$inDir}/{$file}.js";
                $$set .= file_get_contents($filename) . PHP_EOL;

                if ($file == 'wdn') {
                    $$set .= 'WDN.template_path="' . $this->_templatePath . '";' . PHP_EOL;
                } else {
                    if ($file == 'jquery.min') {
                        $$set .= 'WDN.jQuery=jQuery.noConflict(true);';
                    }

                    $$set .= 'WDN.loadedJS["' . $this->_templatePath . $this->_templateDir .
                        $this->_buildTargets['js']['in'] . '/' . $file . '.js"]=1;' . PHP_EOL;
                }
            }

            $all .= $outro;

            // the next line will remove all WDN.log(...); statements
            $all = preg_replace('/WDN\.log\s*\(.+\);/', '', $all);

            file_put_contents("{$outDir}/temp.js", $all);

            $compileCmd = $this->_getCompilerCmd("{$outDir}/temp.js", "{$outDir}/{$outFile}");
            if ($compileCmd) {
                exec($compileCmd, $null, $retVal);
                unset($null);
                if ($retVal !== 0) {
                    $this->_announce('javascript build failed, check the output for other errors');
                    exit($retVal);
                }
                unlink("{$outDir}/temp.js");
            } else {
                unlink("{$outDir}/temp.js");
                $this->_announce('[ERROR] javascript build failed, compiler unknown');
                return false;
            }

            $output = $min . $this->_expandKeywords($outFile, $this->_wdnHeader) . file_get_contents("{$outDir}/{$outFile}");
            file_put_contents("{$outDir}/{$outFile}", $output);
        }

        return true;
    }

    /**
     * Expands keywords in the $input like svn
     *
     * @param unknown_type $file
     * @param unknown_type $input
     * @return mixed
     */
    protected function _expandKeywords($file, $input)
    {
        $output = $input;
        $date = date('D M j G:i:s Y O'); // format to match git default
        $author = trim(`git config --get user.name`);

        $output = str_replace('$Id$', '$Id: ' . implode(' | ', array($file, $date, $author)) . '  $', $input);
        return $output;
    }

    /**
     * Returns the command to execute for compiling javascript files
     * depending on the configured compiler.
     *
     * @param string $in
     * @param string $out
     * @return string|boolean
     */
    protected function _getCompilerCmd($in, $out)
    {
        $cwd = dirname(__FILE__);

        switch($this->_compiler) {
            case self::JS_COMPILER_CLOSURE:
                return "java -jar {$cwd}/bin/compiler.jar --js={$in} --js_output_file={$out}";
                break;
            case self::JS_COMPILER_UGLIFYJS:
                return $this->_getLocalBinCmd($cwd, 'uglifyjs -nc --unsafe', $in, $out);
                break;
            default:
                break;
        }

        return false;
    }

    /**
     * Returns a command to execute that will append the local binary directory
     * to the $PATH and then call the given cmd with input and output params.
     *
     * @param string $cwd The path to the directory with the local binaries
     * @param string $cmd The binary to call
     * @param string $in The path to the input file
     * @param string $out The path to the output file
     * @return string
     */
    protected function _getLocalBinCmd($cwd, $cmd, $in, $out)
    {
        $uname = trim(`uname`);
        return "/usr/bin/env PATH=\"\$PATH:{$cwd}/bin:{$cwd}/bin/{$uname}\" {$cmd} {$in} > {$out}";
    }

    /**
     * Returns an array of css build targets made from combining the named
     * targets and appending all of the media width files
     *
     * @return array
     */
    protected function _getCssTargets()
    {
        $targets = array();
        foreach ($this->_supportedMediaWidths as $width) {
            $targets[] = $width . '.css';
        }

        return array_merge($targets, $this->_buildTargets['css']['files']);
    }

    /**
     * Returns an array of css build prerequisite files for compression
     *
     * @return array
     */
    protected function _getCssPrereqs()
    {
        $files = array();

        // Assemble the array of files needed for compression
        foreach ($this->_cssFiles as $file => $options) {
            if (is_int($file)) {
                $file = $options;
                $options = array();
            }

            if (isset($options['ignore']) && $options['ignore']) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }


    /**
     * Returns an array initialized with empty strings for all
     * supported media widths
     *
     * @return array
     */
    protected function _initMediaSections()
    {
        $media_sections = array();

        foreach ($this->_supportedMediaWidths as $width) {
            $media_sections[$width] = '';
        }

        return $media_sections;
    }

    /**
     * Compresses the template CSS files
     *
     * @return UNL_WDNTemplates_Compressor
     */
    public function buildCss()
    {
        $inDir = $this->_getSrcTemplatePath($this->_buildTargets['css']['in']);
        $outDir = $this->_getSrcTemplatePath($this->_buildTargets['css']['out']);
        $outFiles = $this->_getCssTargets();
        $files = $this->_getCssPrereqs();
        $i = count($outFiles);

        if (!$this->_checkMtime("{$outDir}/{$outFiles[$i - 2]}", $inDir, $files, 'css', 'css')) {
            return $this;
        }

        $this->_announce('Building css targets');

        // All the base styles
        $base             = '';

        // Each section of minimum width css declarations
        $media_sections = $this->_initMediaSections();

        foreach ($files as $file) {
            $dir = '';
            if (strpos($file,'/') !== false) {
                list($dir) = explode('/', $file);
                $dir .= '/';
            }

            $contents = $this->_cleanCssFile(file_get_contents("{$inDir}/{$file}.css"), '../' . $dir);

            // remove comments
            $contents = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $contents);
            // remove tabs, spaces, newlines, etc.
            $contents = str_replace(array("\r\n", "\r", "\n", "\t"), '', $contents);
            $contents = str_replace(array('    ', '   ', '  '), ' ', $contents);
            $contents = str_replace(', ', ',', $contents);
            $contents = str_replace('; ', ';', $contents);
            $contents = str_replace(': ', ':', $contents);
            $contents = str_replace('{ ', '{', $contents);
            $contents = str_replace(' {', '{', $contents);
            $contents = str_replace(' }', '}', $contents);

            // Now we have a clean, compressed individual css file

            // Split into sections for each minimum resolution and base
            $css_sections = explode('@media ', $contents);

            foreach ($css_sections as $section) {
                if (preg_match('/^\(min-width:([\d]+)px\)\{(.*)\}$/', $section, $matches)) {
                    // Found a section
                    if (isset($media_sections[$matches[1]]) && $matches[2] != ' ') {
                        $media_sections[$matches[1]] .= $matches[2];
                    }
                } else {
                    // this is a "base" CSS section
                    $base .= $section;
                }
            }
        }

        file_put_contents("{$outDir}/{$outFiles[$i - 2]}", $this->_expandKeywords($outFiles[$i - 2], $this->_wdnHeader) . $base);

        $i = 0;
        foreach ($media_sections as $min_width => $media_section_css) {
            file_put_contents("{$outDir}/{$outFiles[$i]}",
                $this->_expandKeywords($outFiles[$i], $this->_wdnHeader) . $media_section_css);
            $i++;
        }

        // Now place a single file with all media width sections combined (for IE)
        $i++;
        file_put_contents("{$outDir}/{$outFiles[$i]}",
            $this->_expandKeywords($outFiles[$i], $this->_wdnHeader) . implode(' ', $media_sections));

        $this->_announce('css build complete');

        return $this;
    }

    /**
     * Fixes relative css locations and removes @import directives
     *
     * @param string $css
     * @param string $dir
     * @return string
     */
    protected function _cleanCssFile($css, $dir)
    {
        //converts css paths
        $css = str_replace(
            array('../images/', 'images/', '@IMAGES', 'URWGrotesk/'),
            array('@IMAGES/', $dir . 'images/', $dir . '../images', $dir . 'URWGrotesk/'),
            $css
        );

        return preg_replace('/\@import[\s]+url\(.*\);/', '', $css);
    }

    /**
     * Builds the debug css file from the css prereqs
     *
     * @return UNL_WDNTemplates_Compressor
     */
    public function buildCssDebug()
    {
        $inPath = $this->_buildTargets['css_debug']['in'];
        $outDir = $this->_getSrcTemplatePath($this->_buildTargets['css_debug']['out']);
        $outFiles = $this->_buildTargets['css_debug']['files'];
        $files = $this->_getCssPrereqs();

        $this->_announce('Building debug css target');

        $content = '';

        foreach ($files as $file) {
            $content .= "@import url('{$inPath}{$file}.css');" . PHP_EOL;
        }

        file_put_contents("{$outDir}/{$outFiles[0]}", $this->_debugHeader . $content);

        $this->_announce('debug css build complete');

        return $this;
    }

    protected function _getLessPrereqs()
    {
        $files = array();

        // Assemble the array of files needed for compression
        foreach ($this->_cssFiles as $file => $options) {
            if (is_int($file)) {
                $file = $options;
                $options = array();
            }

            if (isset($options['noless']) && $options['noless']) {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * Compiles the template less files into CSS
     *
     * @return UNL_WDNTemplates_Compressor
     */
    public function buildLess()
    {
        $inDir = $this->_getSrcTemplatePath($this->_buildTargets['less']['in']);
        $outDir = $this->_getSrcTemplatePath($this->_buildTargets['less']['out']);
        $files = $this->_getLessPrereqs();

        $this->_announce('Building less targets');

        foreach ($files as $file) {
            $prereq = "{$inDir}/{$file}.less";
            $target = "{$outDir}/{$file}.css";

            // Check if we need to compile this less file
            if (!$this->_checkMtime($target, $inDir, array($file), 'less', '',
                '[NOTICE] Skipped less target "' . $file . '"')
            ) {
                continue;
            }

            exec($this->_getLocalBinCmd(dirname(__FILE__), 'lessc', $prereq, $target), $null, $retVal);
            unset($null);

            if ($retVal !== 0) {
                $this->_announce('less build failed, check the output for other errors.');
                exit($retVal);
            }
        }

        $this->_announce('less build complete');

        return $this;
    }

    /**
     * A shortcut method for building the javascript, less, and css files.
     *
     * @return UNL_WDNTemplates_Compressor
     */
    public function make()
    {
        return $this->buildJs()
            ->buildCssDebug()
            ->buildLess()
            ->buildCss();
    }

    /**
     * Removes all compiled template files and touches all less files
     *
     * @return UNL_WDNTemplates_Compressor
     */
    public function clean()
    {
        foreach ($this->_buildTargets as $type => $config) {
            if ($type == 'less') {
                $this->_announce('Cleaning target: less, by touching all prereqs');

                // Different clean logic; touches all of the input files
                $outFiles = $this->_getLessPrereqs();
                $outDir = $this->_getSrcTemplatePath($config['in']);

                foreach ($outFiles as $outFile) {
                    if (file_exists("{$outDir}/{$outFile}.less")) {
                        touch("{$outDir}/{$outFile}.less");
                    }
                }

                continue;
            } elseif ($type == 'css') {
                $outFiles = $this->_getCssTargets();
            } else {
                $outFiles = $config['files'];
            }

            $outDir = $this->_getSrcTemplatePath($config['out']);

            $this->_announce('Cleaning target: ' . $type);

            foreach ($outFiles as $outFile) {
                if (file_exists("{$outDir}/{$outFile}")) {
                    unlink("{$outDir}/{$outFile}");
                }
            }
        }

        $this->_announce('Target clean complete');

        return $this;
    }
}

// BEGIN PROGRAM MAIN

$compressor = new UNL_WDNTemplates_Compressor();
$opts = new Zend_Console_Getopt(array(
    'help|h'            => 'Shows this usege help',
    'force|f'           => 'Forces the build by ignoring the file modified times',
    'verbose|v'         => 'Make the build output be verbose',
    'compiler|c=s'      => 'JavaScript compiler option [closure|uglify-js] (defaults to "' . $compressor->getCompiler() . '")',
    'template-dir|d=s'  => 'The path to the template directory (defaults to "' . $compressor->getTemplateDir() . '")',
    'template-path|p=s' => 'The URI path to the templates (defaults to "' . $compressor->getTemplatePath() . '")',
));

try {
    $opts->parse();
} catch (Zend_Console_Getopt_Exception $e) {
    echo $e->getUsageMessage();
    exit(1);
}

if ($opts->getOption('h')) {
    echo $opts->getUsageMessage();
    exit;
}

if ($opts->getOption('f')) {
    $compressor->forceBuild();
}

if ($opts->getOption('v')) {
    $compressor->verbose();
}

if ($opts->getOption('c')) {
    $compressor->setCompiler($opts->getOption('c'));
}

if ($opts->getOption('d')) {
    $compressor->setTemplateDir($opts->getOption('d'));
}

if ($opts->getOption('p')) {
    $compressor->setTemplatePath($opts->getOption('p'));
}

$otherArgs = $opts->getRemainingArgs();
if (empty($otherArgs[0])) {
    $otherArgs[] = 'all';
}

foreach ($otherArgs as $target) {
    switch ($target) {
        case 'all':
            $compressor->make();
            break;
        case 'clean':
            $compressor->clean();
            break;
        case 'debug':
            $compressor->buildCssDebug()
                ->buildLess();
            break;
        case 'javascript':
            $compressor->buildJs();
            break;
        case 'less-css':
            $compressor->buildLess()
                ->buildCss();
            break;
        case 'css':
            $compressor->buildCss();
            break;
        case 'less':
            $compressor->buildLess();
            break;
        default:
            echo 'I do not understand target "' . $target . '". Please provide a valid build target.' . PHP_EOL;
            exit(1);
    }
}

exit;
