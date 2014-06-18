block-generator
===============

Register bundle in AppKernel.php

    public function registerBundles()
    {
    
            new Jse\EMGeneratorBundle\JseEMGeneratorBundle(),
            
    }

command:

php app/console generate:entity:manager
