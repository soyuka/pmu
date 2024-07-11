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

### Blend dependencies

Blend your root `composer.json` constraints in each of the projects. 

```
composer blend [--dev] [project-name]
```

Note: there's no dry mode on this command, use a VCS to rollback on unwanted changes.

When `project-a` depends on `dependency-a:^2.0.0` and your root project has `dependency-a:^3.0.0`, running `composer blend` will set the requirement of `dependency-a` to `^3.0.0` in `project-a`.

We do not check if a dependency is valid, you should probably run `composer all validate` or `composer all update` after running this. 

Blend can also transfer any json path:

```
composer blend --json-path=extra.branch-alias.dev-main --force
```

Where `force` will write even if the value is not present in the project's `composer.json`.

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
