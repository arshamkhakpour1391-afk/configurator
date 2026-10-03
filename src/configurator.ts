import * as core from "@actions/core";
import * as tc from "@actions/tool-cache";
import * as exec from "@actions/exec";
import * as io from "@actions/io";
import * as path from "path";
import * as os from "os";
import { getTag } from "./release";
import Mustache from "mustache";
import { v4 as uuidv4 } from "uuid";
import * as fs from "fs-extra";

const NameInput: string = "name";
const URLInput: string = "url";
const PathInArchiveInput: string = "pathInArchive";

const FromGitHubReleases: string = "fromGitHubReleases";
const Token: string = "token";
const Repo: string = "repo";
const Version: string = "version";
const IncludePrereleases: string = "includePrereleases";
const URLTemplate: string = "urlTemplate";

export function getConfig(): Configurator {
  return new Configurator(
    core.getInput(NameInput),
    core.getInput(URLInput),
    core.getInput(PathInArchiveInput),

    core.getInput(FromGitHubReleases),
    core.getInput(Token),
    core.getInput(Repo),
    core.getInput(Version),
    core.getInput(IncludePrereleases),
    core.getInput(URLTemplate)
  );
}

export class Configurator {
  name: string;
  url: string;
  pathInArchive: string;

  fromGitHubReleases: boolean;
  token: string;
  repo: string;
  version: string;
  includePrereleases: boolean;
  urlTemplate: string;

  constructor(
    name: string,
    url: string,
    pathInArchive: string,
    fromGitHubRelease: string,
    token: string,
    repo: string,
    version: string,
    includePrereleases: string,
    urlTemplate: string
  ) {
    this.name = name;
    this.url = url;
    this.pathInArchive = pathInArchive;

    this.fromGitHubReleases = fromGitHubRelease == "true";
    this.token = token;
    this.repo = repo;
    this.version = version;
    this.includePrereleases = includePrereleases == "true";
    this.urlTemplate = urlTemplate;
  }

  async configure() {
    this.validate();
    let downloadURL: string;
    if (this.fromGitHubReleases) {
      let tag = await getTag(
        this.token,
        this.repo,
        this.version,
        this.includePrereleases
      );

      const rawVersion = tag.startsWith("v") ? tag.slice(1) : tag;
      // Mustache HTML-escapes by default, which corrupts tags that contain
      // '&' or similar and produces a download URL that 404s.
      downloadURL = Mustache.render(
        this.urlTemplate,
        {
          version: tag,
          rawVersion: rawVersion,
        },
        {},
        { escape: (value: string) => value }
      );
    } else {
      downloadURL = this.url;
    }

    console.log(`Downloading tool from ${stripUrlSecrets(downloadURL)}`);
    let downloadPath: string | null = null;
    let archivePath: string | null = null;
    let randomDir: string = uuidv4();
    const tempDir = path.join(os.tmpdir(), "tmp", "runner", randomDir);
    console.log(`Creating tempdir ${tempDir}`);
    await io.mkdirP(tempDir);
    downloadPath = await downloadWithRetry(downloadURL);

    const archiveType = getArchiveType(downloadURL);
    switch (archiveType) {
      case ArchiveType.None:
        await this.moveToPath(downloadPath);
        break;

      case ArchiveType.TarGz:
        archivePath = await tc.extractTar(downloadPath, tempDir);
        await this.moveToPath(path.join(archivePath, this.pathInArchive));
        break;

      case ArchiveType.TarXz:
        archivePath = await tc.extractTar(downloadPath, tempDir, "x");
        await this.moveToPath(path.join(archivePath, this.pathInArchive));
        break;

      case ArchiveType.Tgz:
        archivePath = await tc.extractTar(downloadPath, tempDir);
        await this.moveToPath(path.join(archivePath, this.pathInArchive));
        break;

      case ArchiveType.Zip:
        archivePath = await tc.extractZip(downloadPath, tempDir);
        await this.moveToPath(path.join(archivePath, this.pathInArchive));
        break;

      case ArchiveType.SevenZ:
        archivePath = await tc.extract7z(downloadPath, tempDir);
        await this.moveToPath(path.join(archivePath, this.pathInArchive));
        break;

      case ArchiveType.TarBz2:
        archivePath = await tc.extractTar(downloadPath, tempDir, "xj");
        await this.moveToPath(path.join(archivePath, this.pathInArchive));
        break;

      default:
        throw new Error(`Unsupported archive type for ${downloadURL}`);
    }

    // Clean up the tempdir when done (this step is important for self-hosted runners)
    return io.rmRF(tempDir);
  }

  async moveToPath(downloadPath: string) {
    let toolPath = binPath();
    await io.mkdirP(toolPath);
    const dest = path.join(toolPath, this.name);
    // Replacing a stale binary used to be skipped, so a second run kept the
    // old tool and reported success. Move via a temp name so a failed replace
    // does not delete the previous binary first.
    const staging = `${dest}.incoming-${uuidv4()}`;
    fs.moveSync(downloadPath, staging, { overwrite: true });
    fs.moveSync(staging, dest, { overwrite: true });

    if (process.platform !== "win32") {
      await exec.exec("chmod", ["+x", path.join(toolPath, this.name)]);
    }

    core.addPath(toolPath);
  }

  validate() {
    if (!this.name) {
      throw new Error(
        `"name" is required. This is used to set the executable name of the tool.`
      );
    }

    if (!this.fromGitHubReleases && !this.url) {
      throw new Error(`"url" is required when downloading a tool directly.`);
    }

    if (!this.fromGitHubReleases && !matchesUrlRegex(this.url)) {
      throw new Error(`"url" supplied as input is not a valid URL.`);
    }

    if (this.fromGitHubReleases && !matchesUrlRegex(this.urlTemplate)) {
      throw new Error(`"urlTemplate" supplied as input is not a valid URL.`);
    }

    assertSafeArchivePath(this.pathInArchive);

    if (getArchiveType(this.url) !== ArchiveType.None && !this.pathInArchive) {
      throw new Error(
        `"pathInArchive" is required when "url" points to an archive file`
      );
    }

    if (
      this.fromGitHubReleases &&
      getArchiveType(this.urlTemplate) !== ArchiveType.None &&
      !this.pathInArchive
    ) {
      throw new Error(
        `"pathInArchive" is required when "urlTemplate" points to an archive file.`
      );
    }

    if (
      this.fromGitHubReleases &&
      (!this.token || !this.repo || !this.version || !this.urlTemplate)
    ) {
      throw new Error(
        `if trying to fetch version from GitHub releases, "token", "repo", "version", and "urlTemplate" are required.`
      );
    }
  }
}

export function archivePathname(downloadURL: string): string {
  const noHash = downloadURL.split("#")[0];
  const noQuery = noHash.split("?")[0];
  return noQuery.toLowerCase();
}

export function getArchiveType(downloadURL: string): ArchiveType {
  const pathname = archivePathname(downloadURL);
  if (pathname.endsWith(ArchiveType.TarGz)) return ArchiveType.TarGz;
  if (pathname.endsWith(ArchiveType.TarBz2)) return ArchiveType.TarBz2;
  if (pathname.endsWith(ArchiveType.TarXz)) return ArchiveType.TarXz;
  if (pathname.endsWith(ArchiveType.Tgz)) return ArchiveType.Tgz;
  if (pathname.endsWith(ArchiveType.Zip)) return ArchiveType.Zip;
  if (pathname.endsWith(ArchiveType.SevenZ)) return ArchiveType.SevenZ;

  return ArchiveType.None;
}

export function binPath(): string {
  let baseLocation: string;
  if (process.platform === "win32") {
    // On windows use the USERPROFILE env variable
    baseLocation = process.env["USERPROFILE"] || "C:\\";
  } else {
    if (process.platform === "darwin") {
      baseLocation = "/Users";
    } else {
      baseLocation = "/home";
    }
  }

  let username = "runner";
  try {
    username = os.userInfo().username || username;
  } catch {
    username = process.env["USERNAME"] || process.env["USER"] || username;
  }

  return path.join(baseLocation, username, "configurator", "bin");
}

export enum ArchiveType {
  None = "",
  TarGz = ".tar.gz",
  TarBz2 = ".tar.bz2",
  TarXz = ".tar.xz",
  Tgz = ".tgz",
  Zip = ".zip",
  SevenZ = ".7z",
}

function matchesUrlRegex(input: string): boolean {
  if (!input || /\s/.test(input)) {
    return false;
  }
  // Mustache placeholders are not valid URL characters. Substitute them before
  // checking the host, but keep unusual path characters such as '<' that the
  // historical tests use.
  const probe = input.replace(/\{\{[#/^]?\s*[\w.]+\s*\}\}/g, "v");
  const reg =
    /^(https?:\/\/)([a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?(\/.*)?$/i;
  return reg.test(probe);
}

function assertSafeArchivePath(archivePath: string) {
  if (!archivePath) {
    return;
  }
  const normalized = path.posix.normalize(archivePath.replace(/\\/g, "/"));
  if (
    path.isAbsolute(archivePath) ||
    normalized === ".." ||
    normalized.startsWith("../") ||
    normalized.includes("/../")
  ) {
    throw new Error(
      `"pathInArchive" must be a relative path inside the archive.`
    );
  }
}

async function downloadWithRetry(url: string, attempts = 3): Promise<string> {
  let last: unknown;
  for (let attempt = 1; attempt <= attempts; attempt++) {
    try {
      return await tc.downloadTool(url);
    } catch (error) {
      last = error;
      if (attempt < attempts) {
        await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
      }
    }
  }
  throw last;
}

function stripUrlSecrets(url: string): string {
  return url.replace(/([?&](?:token|access_token|sig|signature)=)[^&]+/gi, "$1***");
}
