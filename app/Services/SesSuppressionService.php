<?php

namespace App\Services;

use App\Models\Project;
use Aws\Exception\AwsException;

class SesSuppressionService
{
    public function __construct(private readonly SesV2ClientFactory $clientFactory) {}

    public function putSuppressedDestination(Project $project, string $email, string $reason): void
    {
        $client = $this->clientFactory->forProject($project);
        $client->putSuppressedDestination([
            'EmailAddress' => $email,
            'Reason' => $reason,
        ]);
    }

    /**
     * @return array{summaries: list<array{email: string, reason: string, last_update_time: ?string}>, next_token: ?string}
     */
    public function listSuppressedDestinations(Project $project, ?string $nextToken = null, int $pageSize = 50): array
    {
        $client = $this->clientFactory->forProject($project);
        $args = ['PageSize' => $pageSize];
        if ($nextToken !== null && $nextToken !== '') {
            $args['NextToken'] = $nextToken;
        }

        $result = $client->listSuppressedDestinations($args);
        $raw = $result->get('SuppressedDestinationSummaries') ?? [];
        $summaries = [];
        foreach ($raw as $row) {
            $summaries[] = [
                'email' => (string) ($row['EmailAddress'] ?? ''),
                'reason' => (string) ($row['Reason'] ?? ''),
                'last_update_time' => isset($row['LastUpdateTime']) ? (string) $row['LastUpdateTime'] : null,
            ];
        }

        return [
            'summaries' => $summaries,
            'next_token' => $result->get('NextToken'),
        ];
    }

    public function deleteSuppressedDestination(Project $project, string $email): void
    {
        $client = $this->clientFactory->forProject($project);
        $client->deleteSuppressedDestination([
            'EmailAddress' => $email,
        ]);
    }

    /**
     * @return array{email: string, reason: string, last_update_time: ?string}
     */
    public function getSuppressedDestination(Project $project, string $email): array
    {
        $client = $this->clientFactory->forProject($project);
        $result = $client->getSuppressedDestination([
            'EmailAddress' => $email,
        ]);
        $row = $result->get('SuppressedDestination') ?? [];

        return [
            'email' => (string) ($row['EmailAddress'] ?? ''),
            'reason' => (string) ($row['Reason'] ?? ''),
            'last_update_time' => isset($row['LastUpdateTime']) ? (string) $row['LastUpdateTime'] : null,
        ];
    }

    /**
     * True if the exception should not trigger further queue retries (e.g. client error / duplicate).
     */
    public static function isNonRetryablePutFailure(AwsException $e): bool
    {
        $code = (string) $e->getAwsErrorCode();

        return in_array($code, ['BadRequestException', 'NotFoundException'], true)
            || $e->getStatusCode() === 400;
    }
}
