<?php

namespace Phthviet\GearmanBundle\Service;

use Mmoreramerino\GearmanBundle\Service\GearmanCacheLoader as BaseGearmanCacheLoader;
use Symfony\Component\Config\FileLocator;
use Mmoreramerino\GearmanBundle\Service\GearmanCache;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Mmoreramerino\GearmanBundle\Module\WorkerCollection;
use Mmoreramerino\GearmanBundle\Module\WorkerDirectoryLoader;
use Mmoreramerino\GearmanBundle\Module\WorkerClass as Worker;

/**
 * Overrite Gearman cache loader class
 *
 * @author Viet Pham <phpcodervn@gmail.com>
 */
class GearmanCacheLoader extends BaseGearmanCacheLoader
{
    public function load(GearmanCache $cache)
    {
        // find MmoreramerinoGearmanBundle's path
        $mmoreramerinoGearmanBundlePath = $this->container->get('kernel')->locateResource('@MmoreramerinoGearmanBundle');

        if (version_compare(\Doctrine\Common\Version::VERSION, '2.2.0-DEV', '>=')) {
            // Register the ORM Annotations in the AnnotationRegistry
            AnnotationRegistry::registerFile($mmoreramerinoGearmanBundlePath . "/Driver/Gearman/Work.php");
            AnnotationRegistry::registerFile($mmoreramerinoGearmanBundlePath . "/Driver/Gearman/Job.php");

            $reader = new \Doctrine\Common\Annotations\SimpleAnnotationReader();
            $reader->addNamespace('Mmoreramerino\GearmanBundle\Driver');
        } else {
            // Register the ORM Annotations in the AnnotationRegistry
            AnnotationRegistry::registerFile($mmoreramerinoGearmanBundlePath . "/Driver/Gearman/Work.php");
            AnnotationRegistry::registerFile($mmoreramerinoGearmanBundlePath . "/Driver/Gearman/Job.php");

            $reader = new AnnotationReader();
            $reader->setDefaultAnnotationNamespace('Mmoreramerino\GearmanBundle\Driver\\');
        }

        $workerCollection = new WorkerCollection;
        $bundles = $this->container->get('kernel')->getBundles();
        $workerDir = 'GearmanWorkers';
        if($this->container->hasParameter('gearman.worker_dir')) {
            $workerDir = $this->container->getParameter('gearman.worker_dir');
        }

        foreach ($bundles as $bundle) {
            if (!\in_array($bundle->getNamespace(), $this->getParseableBundles())) {
                continue;
            }
            if(!is_dir($bundle->getPath() . '/' . $workerDir)) {
                continue;
            }

            $filesLoader = new WorkerDirectoryLoader(new FileLocator('.'));
            $files = $filesLoader->load($bundle->getPath() . '/' . $workerDir);

            foreach ($files as $file) {

                if ($this->isIgnore($file['class'])) {
                    continue;
                }
                $reflClass = new \ReflectionClass($file['class']);
                $classAnnotations = $reader->getClassAnnotations($reflClass);

                foreach ($classAnnotations as $annot) {

                    if ($annot instanceof \Mmoreramerino\GearmanBundle\Driver\Gearman\Work) {
                        $workerCollection->add(new Worker($annot, $reflClass, $reader, $this->getSettings()));
                    }
                }
            }
        }

        return $cache   ->set($workerCollection->__toCache())
                        ->save();
    }
}
