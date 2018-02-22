# FileUploadFormTypeBundle
Helper bundle for mapping Dropzone.js frontend to Symfony FormType using File entity

This bundle was heavily inspired by [sopinet/UploadFilesBundle](https://github.com/sopinet/UploadFilesBundle).

## Installation 

#### Installing prerequisites
Make sure you have [Oneup\UploaderBundle](https://packagist.org/packages/oneup/uploader-bundle) installed and configured
before you proceed.  
See their [documentation](https://github.com/1up-lab/OneupUploaderBundle/blob/master/README.md) for
setting up this bundle. 

___Note:___ Make sure you enable orphanage management by setting the `use_orphanage` configuration setting to `true`.

#### Installing the bundle
Use composer to install the bundle
```bash
$ composer require curious-inc/file-upload-form-type-bundle
```

#### Registering the bundle in AppKernel
Register both OneUp/Uploader bundle and this bundle in AppKernel
```php
//app/AppKernel.php

$bundles = [

  ...

  new Oneup\UploaderBundle\OneupUploaderBundle(),
  new CuriousInc\FileUploadFormTypeBundle\CuriousIncFileUploadFormTypeBundle(),

  ...

]
```

#### Configuring the bundle
##### Routing configuration
Edit your applications routing configuration
```yaml
//app/config/routing.yml;

...

# Routing for CuriousInc FileUploadFormType bundle
curious_file_upload:
    resource: '@CuriousIncFileUploadFormTypeBundle/Resources/config/routing.yml'

...
```

##### Bundle configuration
Edit your applications configuration file to reflect the following changes

Form configuration
```yaml
//app/config/config.yml

...

# Map template to FormType 
twig:
    form_themes:
        - 'CuriousIncFileUploadFormTypeBundle:Form:file.html.twig'

...

```

Form configuration for SonataAdmin (if applicable)
```yaml
//app/config/config.yml

...

# Sonata DoctrineOrmAdmin template overrides
sonata_doctrine_orm_admin:
    templates:
        types:
            list:
                dropzone:  '@CuriousIncFileUploadFormTypeBundle:Admin:file.html.twig'
                ...
            show:
                dropzone:  '@CuriousIncFileUploadFormTypeBundle:Admin:file.html.twig'
                ...

...

```
