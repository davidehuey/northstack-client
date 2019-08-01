<?php


namespace NorthStack\NorthStackClient\Command\Stack;


use GuzzleHttp\Exception\ClientException;
use NorthStack\NorthStackClient\API\Infra\AppClient;
use NorthStack\NorthStackClient\API\Infra\StackClient;
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

class AppListCommand extends Command
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
        OrgAccountHelper $orgAccountHelper
    )
    {
        parent::__construct("stack:app:list");
        $this->orgAccountHelper = $orgAccountHelper;
        $this->orgsClient = $orgsClient;
        $this->stackClient = $stackClient;
        $this->appClient = $appClient;
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('List Stack Apps')
            ->addArgument('stack', InputArgument::REQUIRED, 'Stack label')
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

        try {
            $r = $this->appClient->listApps(
                $this->token->token,
                $stackId
            );
            $this->displayTable($output, json_decode($r->getBody()->getContents())->data, [
                'ID' => 'id',
                'Label' => 'label',
                'Active' => 'active',
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 422) {
                $this->displayValidationErrors($e->getResponse(), $output);
            } else {
                throw $e;
            }
        }
    }
}
