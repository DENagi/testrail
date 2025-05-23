# Codeception TestRail Integration

The extension allows to integrate your test with TestRail.

# Features
 * Provided the ablility to add ```testSuiteId``` and ```testCaseId``` as an annotation in each tests
 * Only the test having the annotation will be fetched from testrail
 * Automatically creates test run with the same name as your Suite and add the test case to testruns
 * Pushes test results
 * Auto closes the run and keep tracks of old results



# Configuration:
 * Password even accepts API Key, to set API key -> Go to My Settings -> API Keys -> Add Keys -> Enter a name -> Generate Key
 * Add the generated API key in the password field as data type string 
```yml

extensions:
    enabled:
        - Codeception\TestRail\TestRailIntegrationExtension
    config:
        Codeception\TestRail\TestRailIntegrationExtension:
            version: 1
            url: "https://trail.example.com/index.php?"
            username: "autotest@example.com"
            password: "password"
            projectId: "1"

```

# TestCase:
```
/**
* @testSuiteId SuiteId eg: 1234
* @testCaseId TestCaseId  eg: 1234
*/
public function foo()

```

# Run
```bash
>  php vendor/bin/codecept {suite} {path} 
Codeception PHP Testing Framework v2.5.4
Powered by PHPUnit 6.5.12 by Sebastian Bergmann and contributors.
Running with seed:


  TestRails integration is enabled. Version: 1.2
...
Time: 4.4 seconds, Memory: 20.25MB

OK (3 tests, 11 assertions)
```
