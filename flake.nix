{
  description = "Apollo Laravel dev shell";

  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";

  outputs =
    { nixpkgs, ... }:
    let
      systems = [
        "x86_64-linux"
        "aarch64-linux"
        "x86_64-darwin"
        "aarch64-darwin"
      ];
      forAllSystems = f: nixpkgs.lib.genAttrs systems (system: f (import nixpkgs { inherit system; }));
    in
    {
      devShells = forAllSystems (
        pkgs:
        let
          php = pkgs.php84;
        in
        {
          default = pkgs.mkShell {
            packages = [
              php
              pkgs.php84Packages.composer
              pkgs.phpactor
              pkgs.phpstan
              pkgs.nodejs
              pkgs.typescript-language-server
            ];
          };
        }
      );
    };
}
