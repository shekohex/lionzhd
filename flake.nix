{
  description = "Lionzhd development environment";
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs = { self, nixpkgs, flake-utils }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        overlays = [ ];
        pkgs = import nixpkgs {
          inherit system overlays;
        };
        pythonPackages = pkgs.python3Packages;
      in
      {
        devShells.default = pkgs.mkShell {
          name = "lionz";
          venvDir = "./.venv";
          nativeBuildInputs = [
            pkgs.pkg-config
          ];
          buildInputs = [
            pythonPackages.venvShellHook
            pythonPackages.python
            pkgs.uv
          ];
          packages = [
            pkgs.meilisearch
          ];
        };
      });
}
