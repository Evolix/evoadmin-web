pipeline {
    agent none
    stages {
        stage('PHPStan (static analysis)') {
	    agent {
		docker {
		    image 'php:8.2-cli'
		}
	    }
            steps {
                script {
                    sh 'curl -fsSL https://github.com/phpstan/phpstan/releases/download/1.10.41/phpstan.phar -o phpstan.phar'
                    sh 'php ./phpstan.phar analyse --configuration=phpstan.neon --memory-limit=512M --error-format=junit > phpstan-results.junit.xml'
                }
            }
            post {
                always {
                    junit 'phpstan-results.junit.xml'
                }
            }
        }
    }
}
