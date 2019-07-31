<?php


namespace NorthStack\NorthStackClient\Command\Stack;

use GuzzleHttp\Exception\ClientException;
use NorthStack\NorthStackClient\API\Infra\AppClient;
use NorthStack\NorthStackClient\API\Infra\DeploymentClient;
use NorthStack\NorthStackClient\API\Infra\StackClient;
use NorthStack\NorthStackClient\API\Infra\StackEnvClient;
use NorthStack\NorthStackClient\API\Orgs\OrgsClient;
use NorthStack\NorthStackClient\Command\Command;
use NorthStack\NorthStackClient\Command\Helpers\OutputFormatterTrait;
use NorthStack\NorthStackClient\Command\Helpers\ValidationErrors;
use NorthStack\NorthStackClient\Command\OauthCommandTrait;
use NorthStack\NorthStackClient\Command\StackCommandTrait;
use NorthStack\NorthStackClient\OrgAccountHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeploymentCreateCommand extends Command
{
    use OauthCommandTrait;
    use StackCommandTrait;
    use OutputFormatterTrait;
    use ValidationErrors;

    /**
     * @var OrgAccountHelper
     */
    private $orgAccountHelper;
    /**
     * @var OrgsClient
     */
    private $orgsClient;

    public function __construct(
        OrgsClient $orgsClient,
        StackClient $stackClient,
        AppClient $appClient,
        StackEnvClient $envClient,
        DeploymentClient $deploymentClient,
        OrgAccountHelper $orgAccountHelper
    )
    {
        parent::__construct("stack:deployment:add");
        $this->orgAccountHelper = $orgAccountHelper;
        $this->orgsClient = $orgsClient;
        $this->envClient = $envClient;
        $this->stackClient = $stackClient;
        $this->deploymentClient = $deploymentClient;
        $this->appClient = $appClient;
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Create New Stack App Deployment')
            ->addArgument('stack', InputArgument::REQUIRED, 'Stack label')
            ->addArgument('env', InputArgument::REQUIRED, 'Stack Environment label')
            ->addArgument('app', InputArgument::REQUIRED, 'App label')
            ->addOption('orgId', null, InputOption::VALUE_REQUIRED, 'Only needed if you have access to multiple organizations');
        $this->addOauthOptions();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $orgId = $input->getOption('orgId') ?: $this->orgAccountHelper->getDefaultOrg()['id'];
        $this->requireLogin($this->orgsClient);

        $stackId = $this->getStackIdForLabel(
            $this->token->token,
            $input->getArgument('stack'),
            $orgId
        );
        $envId = $this->getEnvIdForLabel(
            $this->token->token,
            $input->getArgument('env'),
            $stackId
        );
        $appId = $this->getAppIdForLabel(
            $this->token->token,
            $input->getArgument('app'),
            $stackId
        );

        try {
            $r = $this->deploymentClient->createDeployment(
                $this->token->token,
                $envId,
                $appId
            );
            $this->displayRecord($output, json_decode($r->getBody()->getContents(), true));
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 422) {
                $this->displayValidationErrors($e->getResponse(), $output);
            } else {
                throw $e;
            }
        }
    }
}
