{
  description = "Lionzhd development environment";
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
    flake-utils.url = "github:numtide/flake-utils";
  };

  outputs =
    {
      self,
      nixpkgs,
      flake-utils,
    }:
    flake-utils.lib.eachDefaultSystem (
      system:
      let
        overlays = [ ];
        pkgs = import nixpkgs {
          inherit system overlays;
        };
        stdenv = pkgs.stdenv;
        # Function to create script
        mkScript =
          name: text:
          let
            script = pkgs.writeShellScriptBin name text;
          in
          script;
        myphp = pkgs.php84.override {
          embedSupport = true;
          ztsSupport = true;
          staticSupport = stdenv.hostPlatform.isDarwin;
          zendSignalsSupport = false;
          zendMaxExecutionTimersSupport = stdenv.hostPlatform.isLinux;
        };
        php = myphp.buildEnv {
          extensions = (
            { enabled, all }:
            enabled
            ++ (with all; [
              # Add More PHP Extensions Here
              xdebug
              gd
              redis
              pdo
              pdo_mysql
              gmp
              imagick
              bcmath
              opcache
              intl
              memcached
              sockets
              pcntl
              zip
              zstd
              apcu
            ])
          );
          extraConfig = ''
            date.timezone = "UTC"
            # turn off xdebug by default
            xdebug.mode=off
            #xdebug.mode=debug,develop
            #xdebug.start_with_request=yes
            #xdebug.start_upon_error=yes
            #xdebug.idekey=VSCODE
            # Incresing memory limit
            memory_limit = 1G
            #opcache.enable=1
            #opcache.memory_consumption=512
          '';
        };
        frankenphp = pkgs.frankenphp.override {
          inherit php;
        };
        scripts = [
          (mkScript "a" ''${php}/bin/php artisan "$@"'')
        ];
      in
      {
        devShells.default = pkgs.mkShell {
          name = "lionzhd";
          nativeBuildInputs = [ ];
          buildInputs = [
            # Nodejs
            pkgs.nodePackages.typescript-language-server
            pkgs.nodejs_22
            pkgs.pnpm
            pkgs.uv

            php
            frankenphp
            php.packages.composer
            php.packages.phpstan
            pkgs.blade-formatter
            pkgs.phpactor
            pkgs.meilisearch
            pkgs.aria2
          ];
          packages = [ ] ++ scripts;

          COMPOSER_HOME = ".composer";
        };
      }
    );
}
