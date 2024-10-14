# PMU

![PHP Monorepository Utility](./pmu.png)
PMU (PHP Monorepo Utility) is a Composer plugin specifically designed to facilitate PHP monorepo management. It provides commands for running operations on single or multiple projects, synchronizing dependencies, and blending shared configurations.
PMU simplifies dependency handling and automation across interconnected packages, ensuring efficient development and maintenance in monorepositories.

## Installation

```
composer require --dev soyuka/pmu
composer global require --dev soyuka/pmu # ability to link projects globally
```

## Configuration

```json5
// composer.json
{
  "name": "test/monorepo",
  // Specify the projects that are part of your monorepository
  "extra": {
    "pmu": {
        "projects": ["./packages/*/composer.json"]
    }
  },
  "config": {
      "allow-plugins": {
          "soyuka/pmu": true
      }
  }
}
```

Note that `repositories` are propagated to each project when running commands from the base `composer.json` file. An example is available in the `tests/monorepo` directory.

## Commands 

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
composer blend [--dev] [--all] [--self] [--json-path=JSON-PATH] [--value=VALUE] [project-name]
```

Note: there's no dry mode on this command, use a VCS to rollback on unwanted changes.

When `project-a` depends on `dependency-a:^2.0.0` and your root project has `dependency-a:^3.0.0`, running `composer blend` will set the requirement of `dependency-a` to `^3.0.0` in `project-a`.

We do not check if a dependency is valid, you should probably run `composer all validate` or `composer all update` after running this. 

Blend can also transfer any json path:

```
composer blend --json-path=extra.branch-alias.dev-main --force
```

Or blend a given value:

```
composer blend --json-path=extra.branch-alias.dev-main --force --value=4.x
```

Where `force` will write even if the value is not present in the project's `composer.json`.

When you want to bump all your mono-repository's dependencies and ignore the rest use `--self`, this is quite handy with the `--all` option, on API Platform we use this to align every dependency of our mono-repository (eg: set every version to the ones defined on our root composer.json): 

```
composer blend --all --self
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

### Link 

To link your project's mono-repository dependencies use `composer link`. This will create a temporary composer definition with:

- configured repository on each project's `path`
- add a `@dev` constraint to force composer to run local symlinks
- run `composer update`
- revert the definition to the previous ones (we recommend running this command after setting up a version control system)

You can run this command on a global install to link a directory to the current project:

```
composer global link ../the-mono-repository --working-directory=$(pwd)
```

## TODO:

- create and `affected` graph to be able to run tests on affected projects
