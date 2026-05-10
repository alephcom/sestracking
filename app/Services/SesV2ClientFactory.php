<?php

namespace App\Services;

use App\Models\Project;
use Aws\SesV2\SesV2Client;
use InvalidArgumentException;

class SesV2ClientFactory
{
    public function forProject(Project $project): SesV2Client
    {
        $region = trim((string) ($project->ses_aws_default_region ?: config('services.ses.region') ?: ''));

        if ($region === '') {
            throw new InvalidArgumentException('AWS region is not configured for SES suppression. Set ses_aws_default_region on the project or AWS_DEFAULT_REGION / services.ses.region.');
        }

        if (! $project->hasSesSuppressionAwsCredentials()) {
            throw new InvalidArgumentException('SES suppression requires per-project Access Key ID and Secret Access Key (global .env credentials are not used for this API).');
        }

        return new SesV2Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $project->ses_aws_access_key_id,
                'secret' => $project->ses_aws_secret_access_key,
            ],
        ]);
    }
}
