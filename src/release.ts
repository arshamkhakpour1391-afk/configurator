import * as github from "@actions/github";
import * as semver from "semver";
import {
  ReposListReleasesResponseData,
  ReposGetReleaseResponseData,
} from "@octokit/types";

export async function getTag(
  token: string,
  repo: string,
  version: string,
  includePrereleases: boolean
): Promise<string> {
  // the `version` input field can either be a valid semver version, or a
  // version range. If none of them are valid, throw an error.
  if (
    semver.valid(version) === null &&
    semver.validRange(version) === null &&
    version !== "latest"
  ) {
    throw new Error(
      `version input: ${version} is not a valid semver version or range.`
    );
  }

  let client = github.getOctokit(token);
  let owner = repo.split("/")[0];
  let repoName = repo.split("/")[1];

  // by default, the GitHub releases API returns the first 30 releases.
  // while we haven't found a suitable release, increment the page index
  // and iterate through the results.
  if (!owner || !repoName || repo.split("/").length !== 2) {
    throw new Error(`"repo" must be in owner/name form, got: ${repo}`);
  }

  let result: ReposGetReleaseResponseData | undefined;
  let pageIndex = 1;
  const maxPages = 30;
  while (result === undefined) {
    if (pageIndex > maxPages) {
      throw new Error(
        `cannot find suitable release for version constraint ${version} for repo ${owner}/${repo} after ${maxPages} pages`
      );
    }
    let releases;
    try {
      releases = await client.repos.listReleases({
        owner: owner,
        repo: repoName,
        page: pageIndex,
        per_page: 100,
      });
    } catch (error: any) {
      throw new Error(
        `failed to list releases for ${owner}/${repo}: ${error.message || error}`
      );
    }

    // if result is still undefined and there are no more releases it means there are actually
    // no releases that satisty the version constrained. If this happens, throw an error.
    if (releases.data.length === 0 && result === undefined) {
      throw new Error(
        `cannot find suitable release for version constraint ${version} for repo ${owner}/${repo}`
      );
    }

    result = searchSatisfyingRelease(
      releases.data,
      version,
      includePrereleases
    );
    pageIndex++;
  }

  console.log(
    `selected release version ${result.tag_name}, which can be viewed at ${result.url}`
  );
  return result.tag_name;
}

function searchSatisfyingRelease(
  releases: ReposListReleasesResponseData,
  version: string,
  includePrereleases: boolean
): ReposGetReleaseResponseData | undefined {
  // normally the release array returned by the API should be ordered by the release date, so
  // we iterate through it until we find the latest version that satisfies the version constraint.
  // The initial assumption is that, given that users _should_ want to use recently released versions,
  // and the number of releases is not (normally) very large, the complexity of this loop should not
  // be an issue.
  // However, given that this is an ordered array, we could explore a binary search here.
  for (let i = 0; i < releases.length; i++) {
    if (releases[i].draft) {
      continue;
    }
    if (version === "latest") {
      if (releases[i].prerelease && !includePrereleases) {
        continue;
      }
      return releases[i];
    }

    if (includePrereleases === false && releases[i].prerelease === true) {
      continue;
    }
    try {
      if (
        semver.satisfies(releases[i].tag_name, version, {
          includePrerelease: includePrereleases,
        })
      ) {
        return releases[i];
      }
    } catch {
      // Tags that are not semver (nightly, latest, a date) used to throw and
      // fail the whole lookup. Skip them.
      continue;
    }
  }

  return undefined;
}
