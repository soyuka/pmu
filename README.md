# PMU

![PHP Monorepository Utility](./pmu.png)

PMU is a Composer plugin for PHP Monorepository management. 
## Installation

```
composer req --dev soyuka/pmu
```

## Configuration

```json5
{
  "name": "test/monorepo",
  // Specify the projects that are part of your monorepository
  "extra": {
    "projects": [
      "test/a",
      "test/b",
      "test/c"
    ],
  },
  // Add local path repositories for these projects
  "repositories": [
    {"type": "path", "url": "./packages/*"},
  ],
  "config": {
      "allow-plugins": {
          "soyuka/pmu": true
      }
  }
}
```

Note that `repositories` are propagated to each project when running commands from the base `composer.json` file. An example is available in the `tests/monorepo` directory.

## Commands 

When using the pmu plugin commands, we will force packages to be installed from their local versions. 
TODO: add option to install the tagged versions

### Run a command on a single project

```
composer [project-name] [args]
```

For example: `composer test/a install`.

### Run a command on every projects

```
composer all install
```

Runs `composer install` on every projects.

For example to change the branch alias:

```
composer all config extra.branch-alias.dev-main 3.3.x-dev -vvv
```

### Run a graph of dependencies

```
composer graph [project-name]
```

Example: `composer graph test/a` to see the dependencies for the `test/a` project.

### Checks dependencies

This script reads the code and detect `use` classes. It then checks that the dependencies are correctly mapped in the `require` or `require-dev` of each project.

```
composer check-dependencies
```

## TODO:

- handle `branch-alias`
- bump versions of each project's dependencies
- create and `affected` graph to be able to run tests on affected projects
