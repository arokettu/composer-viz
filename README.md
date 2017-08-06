# ComposerViz

A CLI tool to generate dependency graph by GraphViz inspired by `bundle viz`

## Usage

```
composer-viz [-d|--path PATH] [-o|--output OUTPUT] [-f|--format FORMAT] [--no-dev] [--no-php] [--no-ext] [--no-platform] [--no-pkg-versions] [--no-dep-versions] [--no-versions]
```

`-d|--path PATH`: Set path to the project's `composer.lock` and `composer.json`. If not set, current path will be used  
`-o|--output OUTPUT`: Set output file. If not set, the result will be displayed from temporary file  
`-f|--format FORMAT`: Set output file format. Useful if format is not detected from `--output`  
`--no-dev`: Do not show development dependencies  
`--no-php`: Do not show PHP as dependency (php and php64)  
`--no-ext`: Do not show extensions as dependencies  
`--no-platform`: `--no-php` + `--no-ext`
`--no-pkg-versions`: Do not show package versions on graph vertices  
`--no-dep-versions`: Do not show package versions on graph edges  
`--no-versions`: `--no-pkg-versions` + `--no-dep-versions`
